<?php

namespace App\Domain;

use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Enums\DomainFailureCode;
use App\Enums\DomainStatus;
use App\Models\ChurchDomain;
use Illuminate\Support\Facades\Log;

/**
 * The whole-cycle health evaluator for an already-active or degraded
 * ChurchDomain — see K-DOMAIN-001E §7. Evaluates ownership, routing, and
 * TLS/provider health as pure checks first, derives exactly one outcome,
 * then performs exactly one lifecycle mutation. A successful sub-check must
 * never be able to reset another sub-check's failure before the cycle
 * finishes evaluating.
 *
 * This is infrastructure health only (§19) — no publication/content checks.
 */
final readonly class ChurchDomainHealthCycle
{
    public function __construct(
        private VerifyChurchDomainOwnership $ownership,
        private VerifyChurchDomainRouting $routing,
        private DomainProvisioner $provisioner,
        private ChurchDomainLifecycle $lifecycle,
    ) {}

    public function run(ChurchDomain $domain, string $correlationId): DomainCheckResult
    {
        $result = $this->combine(
            $this->ownership->check($domain),
            $this->routing->check($domain),
            $this->checkTls($domain),
        );

        $priorStatus = $domain->status;

        $updated = match ($result->outcome) {
            HealthOutcome::Healthy => $this->lifecycle->healthCycleSucceeded($domain, $correlationId),
            HealthOutcome::ConfirmedUnhealthy => $this->applyConfirmedFailure($domain, $result->failureCode ?? DomainFailureCode::DnsMismatch, $correlationId),
            HealthOutcome::Indeterminate => $this->lifecycle->healthCycleIndeterminate($domain, $correlationId),
        };

        $this->log($result, $domain, $updated, $priorStatus, $correlationId);

        return $result;
    }

    private function applyConfirmedFailure(ChurchDomain $domain, DomainFailureCode $code, string $correlationId): ChurchDomain
    {
        $updated = $this->lifecycle->healthCycleFailed($domain, $code, $correlationId);

        if ($updated->status !== DomainStatus::Degraded && $updated->isDueForAutomaticDegradation()) {
            $updated = $this->lifecycle->degrade($updated, $code, $correlationId);
        }

        return $updated;
    }

    private function checkTls(ChurchDomain $domain): DomainCheckResult
    {
        return match ($this->provisioner->checkTlsStatus($domain)) {
            ProvisioningStatus::Ready => DomainCheckResult::healthy(),
            ProvisioningStatus::Pending => DomainCheckResult::indeterminate(DomainFailureCode::CertificatePending),
            ProvisioningStatus::Failed => DomainCheckResult::unhealthy(DomainFailureCode::CertificateFailed),
            ProvisioningStatus::Unavailable => DomainCheckResult::indeterminate(DomainFailureCode::ProviderUnavailable),
        };
    }

    /**
     * Priority order ownership > routing > TLS decides both the "most
     * relevant" confirmed failure code (§9) and, absent any confirmed
     * failure, which indeterminate reason is reported — an operational
     * judgment call, not a security-sensitive matching rule.
     */
    private function combine(DomainCheckResult $ownership, DomainCheckResult $routing, DomainCheckResult $tls): DomainCheckResult
    {
        foreach ([$ownership, $routing, $tls] as $result) {
            if ($result->outcome === HealthOutcome::ConfirmedUnhealthy) {
                return $result;
            }
        }

        foreach ([$ownership, $routing, $tls] as $result) {
            if ($result->outcome === HealthOutcome::Indeterminate) {
                return $result;
            }
        }

        return DomainCheckResult::healthy();
    }

    private function log(DomainCheckResult $result, ChurchDomain $domain, ChurchDomain $updated, DomainStatus $priorStatus, string $correlationId): void
    {
        $context = [
            'church_domain_id' => $domain->id,
            'church_domain_uuid' => $domain->uuid,
            'church_id' => $domain->church_id,
            'normalized_hostname' => $domain->normalized_hostname,
            'correlation_id' => $correlationId,
            'failure_code' => $updated->failure_code,
            'consecutive_failures' => $updated->consecutive_failures,
            'failure_streak_started_at' => $updated->failure_streak_started_at?->toIso8601String(),
            'prior_status' => $priorStatus->value,
            'resulting_status' => $updated->status->value,
        ];

        match ($result->outcome) {
            // Operational visibility failures and confirmed problems are
            // both worth a warning — neither is routine (§24/§26).
            HealthOutcome::Indeterminate => Log::warning('domain.health_check.indeterminate', $context),
            HealthOutcome::ConfirmedUnhealthy => Log::warning('domain.health_check.confirmed_unhealthy', $context),
            // Ordinary healthy checks: last_checked_at already provides
            // evidence — no log (§24), unless this was a recovery below.
            HealthOutcome::Healthy => null,
        };

        if ($priorStatus === DomainStatus::Degraded && $updated->status === DomainStatus::Degraded) {
            // Still degraded — the confirmed_unhealthy warning above already covers it.
        } elseif ($updated->status === DomainStatus::Degraded) {
            Log::warning('domain.health_check.degraded', $context);
        } elseif ($priorStatus === DomainStatus::Degraded && $updated->status === DomainStatus::Active) {
            Log::info('domain.health_check.recovered', $context);
        }
    }
}

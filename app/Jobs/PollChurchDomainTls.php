<?php

namespace App\Jobs;

use App\Domain\ChurchDomainLifecycle;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Enums\DomainFailureCode;
use App\Enums\DomainTlsStatus;
use App\Models\Church;
use App\Models\ChurchDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The real operational call site for DomainProvisioner::checkTlsStatus()
 * (K-DOMAIN-001E §15) — polls a domain still in Provisioning. Ready
 * activates (if prerequisites still hold); Pending waits for the next
 * poll; Failed is terminal; Unavailable is a transient provider outage,
 * never a certificate failure (§16).
 */
class PollChurchDomainTls implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 20;

    // Shorter than the polling cadence — only prevents overlap (§22).
    public int $uniqueFor = 240;

    public function __construct(public readonly int $domainId, public readonly string $correlationId = '') {}

    public function uniqueId(): string
    {
        return (string) $this->domainId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 90];
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('domain-provider')];
    }

    public function handle(DomainProvisioner $provisioner, ChurchDomainLifecycle $lifecycle): void
    {
        $domain = ChurchDomain::withoutGlobalScope('church_tenant')->find($this->domainId);

        if ($domain === null
            || $domain->tls_status !== DomainTlsStatus::Provisioning
            || $domain->disabled_at !== null
            || $domain->released_at !== null) {
            return; // No longer a provisioning-poll candidate — safe no-op.
        }

        $correlation = $this->correlationId !== '' ? $this->correlationId : (string) Str::uuid();
        // An actual provider check is about to happen — last_checked_at
        // must move for every outcome below (K-DOMAIN-001E-R1 §3), which is
        // why this line only runs once we're certain checkTlsStatus() will
        // actually be called (i.e. past every earlier no-op return).
        $status = $provisioner->checkTlsStatus($domain);

        match ($status) {
            ProvisioningStatus::Ready => $this->activate($domain, $lifecycle, $correlation),
            ProvisioningStatus::Pending => $lifecycle->recordTlsCheck($domain, $correlation),
            ProvisioningStatus::Failed => $lifecycle->tlsFailed($domain, DomainFailureCode::CertificateFailed, $correlation, recordCheck: true),
            ProvisioningStatus::Unavailable => $this->logUnavailable($domain, $correlation, $lifecycle),
        };
    }

    private function activate(ChurchDomain $domain, ChurchDomainLifecycle $lifecycle, string $correlation): void
    {
        $ready = $lifecycle->tlsReady($domain, $correlation, recordCheck: true);

        // Time has passed since this domain entered Provisioning — unlike
        // the initial-verification job, re-check the one prerequisite that
        // could plausibly have changed since (§16).
        $church = Church::query()->find($ready->church_id);
        if ($church !== null && $church->is_active) {
            $lifecycle->activate($ready, correlationId: $correlation);
        }
    }

    private function logUnavailable(ChurchDomain $domain, string $correlation, ChurchDomainLifecycle $lifecycle): void
    {
        // A provider outage is not a certificate failure — remain
        // Provisioning; the next scheduled poll will retry (§16). A real
        // check still occurred, so last_checked_at moves; the confirmed-
        // failure streak is untouched (§3).
        $lifecycle->recordTlsCheck($domain, $correlation);

        Log::warning('domain.tls_poll.provider_unavailable', [
            'church_domain_id' => $domain->id,
            'church_domain_uuid' => $domain->uuid,
            'church_id' => $domain->church_id,
            'normalized_hostname' => $domain->normalized_hostname,
            'correlation_id' => $correlation,
        ]);
    }
}

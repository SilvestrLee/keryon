<?php

namespace App\Domain;

use App\Domain\Dns\DnsLookupStatus;
use App\Domain\Dns\DnsResolver;
use App\Enums\DomainFailureCode;
use App\Models\ChurchDomain;

final readonly class VerifyChurchDomainOwnership
{
    public function __construct(private DnsResolver $dns, private ChurchDomainLifecycle $lifecycle) {}

    /**
     * Pure evaluation — no lifecycle mutation. Reused by both the initial
     * verification flow (execute()) and the ongoing health cycle
     * (ChurchDomainHealthCycle) so the TXT-matching rule lives in one place.
     */
    public function check(ChurchDomain $domain): DomainCheckResult
    {
        $result = $this->dns->txt('_keryon-verification.'.$domain->normalized_hostname);

        if ($result->status === DnsLookupStatus::Found
            && collect($result->values)->contains(fn (string $value): bool => hash_equals($domain->verification_token_hash, hash('sha256', trim($value))))) {
            return DomainCheckResult::healthy();
        }

        return match ($result->status) {
            DnsLookupStatus::Timeout => DomainCheckResult::indeterminate(DomainFailureCode::DnsLookupTimeout),
            DnsLookupStatus::Unavailable => DomainCheckResult::indeterminate(DomainFailureCode::ProviderUnavailable),
            DnsLookupStatus::NotFound => DomainCheckResult::unhealthy(DomainFailureCode::DnsNotConfigured),
            DnsLookupStatus::Found => DomainCheckResult::unhealthy(DomainFailureCode::DnsMismatch),
        };
    }

    public function execute(ChurchDomain $domain, ?string $correlationId = null): bool
    {
        $result = $this->check($domain);

        if ($result->outcome === HealthOutcome::Healthy) {
            $this->lifecycle->ownershipVerified($domain, correlationId: $correlationId);

            return true;
        }

        $this->lifecycle->verificationFailed($domain, $result->failureCode ?? DomainFailureCode::DnsMismatch, $correlationId);

        return false;
    }
}

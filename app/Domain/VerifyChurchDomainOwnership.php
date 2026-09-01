<?php

namespace App\Domain;

use App\Domain\Dns\DnsLookupStatus;
use App\Domain\Dns\DnsResolver;
use App\Enums\DomainFailureCode;
use App\Models\ChurchDomain;

final readonly class VerifyChurchDomainOwnership
{
    public function __construct(private DnsResolver $dns, private ChurchDomainLifecycle $lifecycle) {}

    public function execute(ChurchDomain $domain, ?string $correlationId = null): bool
    {
        $result = $this->dns->txt('_keryon-verification.'.$domain->normalized_hostname);

        if ($result->status === DnsLookupStatus::Found
            && collect($result->values)->contains(fn (string $value): bool => hash_equals($domain->verification_token_hash, hash('sha256', trim($value))))) {
            $this->lifecycle->ownershipVerified($domain, correlationId: $correlationId);

            return true;
        }

        $this->lifecycle->verificationFailed($domain, match ($result->status) {
            DnsLookupStatus::Timeout => DomainFailureCode::DnsLookupTimeout,
            DnsLookupStatus::Unavailable => DomainFailureCode::ProviderUnavailable,
            DnsLookupStatus::NotFound => DomainFailureCode::DnsNotConfigured,
            default => DomainFailureCode::DnsMismatch,
        }, $correlationId);

        return false;
    }
}

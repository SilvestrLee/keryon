<?php

namespace App\Domain;

use App\Enums\DomainFailureCode;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Models\ChurchDomain;

final class ChurchDomainStatusPresenter
{
    public function statusLabel(ChurchDomain $domain): string
    {
        return match ($domain->status) {
            DomainStatus::PendingVerification => 'Setup required',
            DomainStatus::Verified => 'Domain verified',
            DomainStatus::Active => 'Connected',
            DomainStatus::Degraded => 'Connection issue',
            DomainStatus::Disabled => 'Disabled',
            DomainStatus::Released => 'Released',
        };
    }

    public function tlsLabel(ChurchDomain $domain): string
    {
        return match ($domain->tls_status) {
            DomainTlsStatus::NotStarted => 'Not started',
            DomainTlsStatus::Provisioning => 'Setting up HTTPS',
            DomainTlsStatus::Ready => 'HTTPS ready',
            DomainTlsStatus::Failed => 'HTTPS needs attention',
        };
    }

    public function failureMessage(ChurchDomain $domain): ?string
    {
        $failure = $domain->failure_code === null ? null : DomainFailureCode::tryFrom($domain->failure_code);

        return match ($failure) {
            DomainFailureCode::DnsNotConfigured => 'The required DNS record is not visible yet. DNS changes can take some time to appear.',
            DomainFailureCode::DnsMismatch => 'The DNS record does not match the value Keryon expects.',
            DomainFailureCode::DnsLookupTimeout => 'We could not check your DNS right now. Try again shortly.',
            DomainFailureCode::CertificatePending => 'HTTPS is still being prepared.',
            DomainFailureCode::CertificateFailed => 'HTTPS setup needs attention.',
            DomainFailureCode::ProviderUnavailable => 'Domain verification is temporarily unavailable.',
            DomainFailureCode::Disabled => 'This domain is not currently connected.',
            default => null,
        };
    }

    /** @return array<int, array{label: string, complete: bool}> */
    public function steps(ChurchDomain $domain): array
    {
        return [
            ['label' => 'Domain added', 'complete' => true],
            ['label' => 'Ownership verified', 'complete' => $domain->ownership_verified_at !== null],
            ['label' => 'Domain connected', 'complete' => $domain->routing_verified_at !== null],
            ['label' => 'HTTPS ready', 'complete' => $domain->tls_status === DomainTlsStatus::Ready && $domain->tls_ready_at !== null],
            ['label' => 'Active', 'complete' => $domain->isEligible()],
        ];
    }

    public function canRegenerate(ChurchDomain $domain): bool
    {
        return in_array($domain->status, [DomainStatus::PendingVerification, DomainStatus::Verified], true);
    }
}

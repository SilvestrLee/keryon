<?php

namespace App\Jobs;

use App\Domain\ChurchDomainLifecycle;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Domain\VerifyChurchDomainOwnership;
use App\Domain\VerifyChurchDomainRouting;
use App\Enums\ChurchDomainEventType;
use App\Enums\DomainFailureCode;
use App\Models\ChurchDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class VerifyChurchDomain implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 20;

    public function __construct(public readonly int $domainId, public readonly string $correlationId = '') {}

    public function uniqueId(): string
    {
        return (string) $this->domainId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        VerifyChurchDomainOwnership $ownership,
        VerifyChurchDomainRouting $routing,
        DomainProvisioner $provisioner,
        ChurchDomainLifecycle $lifecycle,
    ): void {
        $domain = ChurchDomain::withoutGlobalScope('church_tenant')->findOrFail($this->domainId);
        $correlation = $this->correlationId !== '' ? $this->correlationId : (string) Str::uuid();
        $lifecycle->record($domain, ChurchDomainEventType::VerificationStarted, correlationId: $correlation);

        if (! $ownership->execute($domain, $correlation)) {
            return;
        }
        $domain->refresh();
        if (! $routing->execute($domain, $correlation)) {
            return;
        }

        $domain->refresh();
        $lifecycle->tlsProvisioning($domain, $correlation);
        $status = $provisioner->requestTlsProvisioning($domain->refresh(), $correlation);

        if ($status === ProvisioningStatus::Ready) {
            $domain = $lifecycle->tlsReady($domain->refresh(), $correlation);
            $lifecycle->activate($domain, correlationId: $correlation);
        } elseif ($status === ProvisioningStatus::Failed) {
            $lifecycle->tlsFailed($domain->refresh(), DomainFailureCode::CertificateFailed, $correlation);
        } elseif ($status === ProvisioningStatus::Unavailable) {
            $lifecycle->tlsFailed($domain->refresh(), DomainFailureCode::ProviderUnavailable, $correlation);
        }
    }
}

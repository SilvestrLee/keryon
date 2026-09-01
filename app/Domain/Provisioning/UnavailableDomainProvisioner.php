<?php

namespace App\Domain\Provisioning;

use App\Models\ChurchDomain;

final class UnavailableDomainProvisioner implements DomainProvisioner
{
    public function requestTlsProvisioning(ChurchDomain $domain, string $idempotencyKey): ProvisioningStatus
    {
        return ProvisioningStatus::Unavailable;
    }

    public function checkTlsStatus(ChurchDomain $domain): ProvisioningStatus
    {
        return ProvisioningStatus::Unavailable;
    }

    public function deactivate(ChurchDomain $domain): void {}
}

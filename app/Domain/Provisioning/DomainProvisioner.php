<?php

namespace App\Domain\Provisioning;

use App\Models\ChurchDomain;

interface DomainProvisioner
{
    public function requestTlsProvisioning(ChurchDomain $domain, string $idempotencyKey): ProvisioningStatus;

    public function checkTlsStatus(ChurchDomain $domain): ProvisioningStatus;

    public function deactivate(ChurchDomain $domain): void;
}

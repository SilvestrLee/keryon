<?php

namespace App\Domain\Provisioning;

use App\Models\ChurchDomain;
use LogicException;

final class FakeDomainProvisioner implements DomainProvisioner
{
    private ProvisioningStatus $status = ProvisioningStatus::Pending;

    /** @var array<string,true> */
    private array $requests = [];

    public function __construct()
    {
        if (app()->environment('production')) {
            throw new LogicException('The fake domain provisioner cannot run in production.');
        }
    }

    public function advanceTo(ProvisioningStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }

    public function requestTlsProvisioning(ChurchDomain $domain, string $idempotencyKey): ProvisioningStatus
    {
        $this->requests[$idempotencyKey] = true;

        return $this->status;
    }

    public function checkTlsStatus(ChurchDomain $domain): ProvisioningStatus
    {
        return $this->status;
    }

    public function deactivate(ChurchDomain $domain): void {}
}

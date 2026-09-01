<?php

namespace App\Domain;

use App\Domain\Provisioning\DomainProvisioner;
use App\Models\ChurchDomain;

final readonly class DisableChurchDomain
{
    public function __construct(
        private DomainMutationAuthorizer $authorizer,
        private ChurchDomainLifecycle $lifecycle,
        private DomainProvisioner $provisioner,
    ) {}

    public function execute(ChurchDomain $domain): ChurchDomain
    {
        $membership = $this->authorizer->authorize($domain);
        $this->provisioner->deactivate($domain);

        return $this->lifecycle->disable($domain, $membership);
    }
}

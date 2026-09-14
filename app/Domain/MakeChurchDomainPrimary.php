<?php

namespace App\Domain;

use App\Models\ChurchDomain;

final readonly class MakeChurchDomainPrimary
{
    public function __construct(
        private DomainMutationAuthorizer $authorizer,
        private CustomDomainEntitlementGuard $entitlementGuard,
        private ChurchDomainLifecycle $lifecycle,
    ) {}

    public function execute(ChurchDomain $domain): ChurchDomain
    {
        $membership = $this->authorizer->authorize($domain);
        $this->entitlementGuard->assertAllowed($domain);

        return $this->lifecycle->makePrimary($domain, $membership);
    }
}

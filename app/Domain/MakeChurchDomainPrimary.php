<?php

namespace App\Domain;

use App\Models\ChurchDomain;

final readonly class MakeChurchDomainPrimary
{
    public function __construct(private DomainMutationAuthorizer $authorizer, private ChurchDomainLifecycle $lifecycle) {}

    public function execute(ChurchDomain $domain): ChurchDomain
    {
        return $this->lifecycle->makePrimary($domain, $this->authorizer->authorize($domain));
    }
}

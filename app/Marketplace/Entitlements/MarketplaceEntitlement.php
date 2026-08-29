<?php

namespace App\Marketplace\Entitlements;

use App\Models\ChurchMembership;
use App\Models\MarketplaceItem;

interface MarketplaceEntitlement
{
    public function decide(MarketplaceItem $item, ChurchMembership $membership): MarketplaceEntitlementDecision;
}

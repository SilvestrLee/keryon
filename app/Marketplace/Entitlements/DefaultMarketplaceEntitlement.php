<?php

namespace App\Marketplace\Entitlements;

use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplaceAcquisitionBasis;
use App\Models\ChurchMembership;
use App\Models\MarketplaceItem;

class DefaultMarketplaceEntitlement implements MarketplaceEntitlement
{
    public function decide(MarketplaceItem $item, ChurchMembership $membership): MarketplaceEntitlementDecision
    {
        if ($item->access_type === MarketplaceAccessType::FREE) {
            return MarketplaceEntitlementDecision::allow(MarketplaceAcquisitionBasis::FREE_INCLUDED);
        }

        return MarketplaceEntitlementDecision::deny('premium_entitlement_unavailable');
    }
}

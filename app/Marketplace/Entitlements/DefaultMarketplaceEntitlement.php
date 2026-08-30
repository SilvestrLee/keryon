<?php

namespace App\Marketplace\Entitlements;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\EntitlementKey;
use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplaceAcquisitionBasis;
use App\Models\ChurchMembership;
use App\Models\MarketplaceItem;

class DefaultMarketplaceEntitlement implements MarketplaceEntitlement
{
    public function __construct(private readonly EntitlementResolver $entitlements) {}

    public function decide(MarketplaceItem $item, ChurchMembership $membership): MarketplaceEntitlementDecision
    {
        if ($item->access_type === MarketplaceAccessType::FREE) {
            return MarketplaceEntitlementDecision::allow(MarketplaceAcquisitionBasis::FREE_INCLUDED);
        }

        $decision = $this->entitlements->decision(
            $membership->church,
            EntitlementKey::MarketplacePremiumEnabled,
        );

        if (! $decision->allows()) {
            return MarketplaceEntitlementDecision::deny('premium_entitlement_unavailable');
        }

        return MarketplaceEntitlementDecision::allow(
            MarketplaceAcquisitionBasis::PREMIUM_ENTITLEMENT,
            "plan-version:{$decision->planVersionId}:{$decision->entitlement->value}",
        );
    }
}

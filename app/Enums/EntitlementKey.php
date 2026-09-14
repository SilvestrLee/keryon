<?php

namespace App\Enums;

enum EntitlementKey: string
{
    case FaithFlowEnabled = 'faithflow.enabled';
    case WebsiteEnabled = 'website.enabled';
    case DesignEnabled = 'design.enabled';
    case MarketplacePremiumEnabled = 'marketplace.premium.enabled';
    // K-DOMAIN-001F — governs connecting/activating a Church-owned external
    // hostname only. The first-party {church}.keryon.app address is
    // fundamental and is never gated by this key (see WebsiteEnabled for
    // the Website product itself). Not yet assigned to any PlanVersion —
    // see the K-DOMAIN-001F report §B for the commercial-mapping decision
    // this milestone deliberately leaves to Product Office.
    case WebsiteCustomDomainEnabled = 'website.custom_domain.enabled';

    public function valueType(): EntitlementValueType
    {
        return EntitlementValueType::BOOLEAN;
    }
}

<?php

namespace App\Enums;

enum EntitlementKey: string
{
    case FaithFlowEnabled = 'faithflow.enabled';
    case WebsiteEnabled = 'website.enabled';
    case DesignEnabled = 'design.enabled';
    case MarketplacePremiumEnabled = 'marketplace.premium.enabled';

    public function valueType(): EntitlementValueType
    {
        return EntitlementValueType::BOOLEAN;
    }
}

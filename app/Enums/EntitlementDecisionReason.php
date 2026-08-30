<?php

namespace App\Enums;

enum EntitlementDecisionReason: string
{
    case ALLOWED = 'allowed';
    case ENTITLEMENT_MISSING = 'entitlement_missing';
    case ENTITLEMENT_DISABLED = 'entitlement_disabled';
    case LIMIT_AVAILABLE = 'limit_available';
    case NO_PRODUCT_ASSIGNMENT = 'no_product_assignment';
    case PLAN_VERSION_INACTIVE = 'plan_version_inactive';
}

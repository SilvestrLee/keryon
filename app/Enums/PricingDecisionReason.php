<?php

namespace App\Enums;

enum PricingDecisionReason: string
{
    case RESOLVED = 'resolved';
    case MARKET_UNRESOLVED = 'market_unresolved';
    case MARKET_INACTIVE = 'market_inactive';
    case PLAN_VERSION_INELIGIBLE = 'plan_version_ineligible';
    case PRICE_BOOK_UNAVAILABLE = 'price_book_unavailable';
    case PRICE_UNAVAILABLE = 'price_unavailable';
    case CURRENCY_MISMATCH = 'currency_mismatch';
}

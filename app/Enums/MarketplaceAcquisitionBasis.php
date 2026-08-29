<?php

namespace App\Enums;

enum MarketplaceAcquisitionBasis: string
{
    case FREE_INCLUDED = 'free_included';
    case PREMIUM_ENTITLEMENT = 'premium_entitlement';
}

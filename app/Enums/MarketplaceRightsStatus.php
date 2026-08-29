<?php

namespace App\Enums;

enum MarketplaceRightsStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case REVOKED = 'revoked';
}

<?php

namespace App\Enums;

enum AssetRightsStatus: string
{
    case Unverified = 'unverified';
    case Declared = 'declared';
    case Verified = 'verified';
    case Restricted = 'restricted';
    case Expired = 'expired';
    case Disputed = 'disputed';
    case Withdrawn = 'withdrawn';
}

<?php

namespace App\Enums;

enum MarketplaceSourceValidationStatus: string
{
    case PENDING = 'pending';
    case VALID = 'valid';
    case INVALID = 'invalid';
}

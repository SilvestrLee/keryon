<?php

namespace App\Enums;

enum MarketplaceSourceAvailability: string
{
    case DRAFT = 'draft';
    case AVAILABLE = 'available';
    case WITHDRAWN = 'withdrawn';
}

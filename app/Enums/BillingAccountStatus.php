<?php

namespace App\Enums;

enum BillingAccountStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

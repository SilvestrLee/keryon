<?php

namespace App\Enums;

enum BillingInterval: string
{
    case MONTHLY = 'monthly';
    case ANNUAL = 'annual';
}

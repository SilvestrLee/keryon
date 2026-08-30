<?php

namespace App\Enums;

enum BillingAccountOwnerType: string
{
    case CHURCH = 'church';
    case ORGANIZATION = 'organization';
    case ORGANIZATION_UNIT = 'organization_unit';
}

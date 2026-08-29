<?php

namespace App\Enums;

enum OrganizationMembershipStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REMOVED = 'removed';
}

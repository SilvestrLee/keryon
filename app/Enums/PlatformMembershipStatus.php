<?php

namespace App\Enums;

enum PlatformMembershipStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REMOVED = 'removed';
}

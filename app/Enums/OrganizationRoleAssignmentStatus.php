<?php

namespace App\Enums;

enum OrganizationRoleAssignmentStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REMOVED = 'removed';
}

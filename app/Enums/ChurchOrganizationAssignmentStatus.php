<?php

namespace App\Enums;

enum ChurchOrganizationAssignmentStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case ENDED = 'ended';
}

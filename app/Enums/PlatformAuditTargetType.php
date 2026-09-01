<?php

namespace App\Enums;

enum PlatformAuditTargetType: string
{
    case PLATFORM_MEMBERSHIP = 'platform_membership';
    case CHURCH = 'church';
    case CHURCH_ACTIVATION = 'church_activation';
    case CHURCH_DOMAIN = 'church_domain';
}

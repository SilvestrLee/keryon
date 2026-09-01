<?php

namespace App\Enums;

enum PlatformAuditEventType: string
{
    case PLATFORM_ACCESS_GRANTED = 'platform.access.granted';
    case PLATFORM_ACCESS_SUSPENDED = 'platform.access.suspended';
    case PLATFORM_ACCESS_REMOVED = 'platform.access.removed';
}

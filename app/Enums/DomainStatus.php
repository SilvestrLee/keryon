<?php

namespace App\Enums;

enum DomainStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Active = 'active';
    case Degraded = 'degraded';
    case Disabled = 'disabled';
    case Released = 'released';
}

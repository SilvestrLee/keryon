<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case ENDED = 'ended';

    public function isEffective(): bool
    {
        return in_array($this, [self::TRIALING, self::ACTIVE], true);
    }
}

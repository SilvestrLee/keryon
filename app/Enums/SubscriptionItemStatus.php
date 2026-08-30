<?php

namespace App\Enums;

enum SubscriptionItemStatus: string
{
    case ACTIVE = 'active';
    case ENDED = 'ended';
}

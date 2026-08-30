<?php

namespace App\Enums;

enum CheckoutIntentStatus: string
{
    case PREPARED = 'prepared';
    case INITIALIZED = 'initialized';
    case RECONCILED = 'reconciled';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
}

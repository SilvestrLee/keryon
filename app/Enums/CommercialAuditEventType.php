<?php

namespace App\Enums;

enum CommercialAuditEventType: string
{
    case BILLING_ACCOUNT_CREATED = 'billing.account.created';
    case BILLING_ACCOUNT_DEACTIVATED = 'billing.account.deactivated';
    case TRIAL_STARTED = 'subscription.trial_started';
    case SUBSCRIPTION_ACTIVATED = 'subscription.activated';
    case SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    case SUBSCRIPTION_ENDED = 'subscription.ended';
    case SUBSCRIPTION_PAYER_CHANGED = 'subscription.payer_changed';
}

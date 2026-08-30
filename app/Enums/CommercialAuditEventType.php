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
    case INVOICE_DRAFTED = 'invoice.drafted';
    case INVOICE_ISSUED = 'invoice.issued';
    case INVOICE_VOIDED = 'invoice.voided';
    case PAYMENT_RECORDED = 'payment.recorded';
    case PAYMENT_SUCCEEDED = 'payment.succeeded';
    case PAYMENT_FAILED = 'payment.failed';
    case PAYMENT_ALLOCATED = 'payment.allocated';
    case SUBSCRIPTION_RENEWED = 'subscription.renewed';
    case SUBSCRIPTION_TRIAL_CONVERTED = 'subscription.trial_converted';
}

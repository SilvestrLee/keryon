<?php

namespace App\Enums;

enum PaymentProviderReferenceType: string
{
    case BILLING_ACCOUNT_CUSTOMER = 'billing_account_customer';
    case SUBSCRIPTION = 'subscription';
    case INVOICE = 'invoice';
    case PAYMENT = 'payment';
    case CHECKOUT_SESSION = 'checkout_session';
}

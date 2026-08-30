<?php

namespace App\Enums;

enum PaymentSource: string
{
    case MANUAL_BANK_TRANSFER = 'manual_bank_transfer';
    case PROVIDER = 'provider';
}

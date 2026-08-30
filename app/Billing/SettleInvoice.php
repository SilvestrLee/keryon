<?php

namespace App\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class SettleInvoice
{
    public function __construct(private readonly AllocatePayment $allocations) {}

    public function execute(Payment $payment, Invoice $invoice, int $amountMinor, string $idempotencyKey, ?int $actorUserId, string $origin): PaymentAllocation
    {
        return $this->allocations->execute($payment, $invoice, $amountMinor, $idempotencyKey, $actorUserId, $origin);
    }
}

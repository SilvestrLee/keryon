<?php

namespace App\Billing;

use App\Enums\CommercialAuditEventType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use DomainException;
use Illuminate\Support\Facades\DB;

class AllocatePayment
{
    public function execute(Payment $payment, Invoice $invoice, int $amountMinor, string $idempotencyKey, ?int $actorUserId, string $origin): PaymentAllocation
    {
        if ($amountMinor <= 0 || $origin === '') {
            throw new DomainException('Allocation requires a positive amount and origin.');
        }

        return DB::transaction(function () use ($payment, $invoice, $amountMinor, $idempotencyKey, $actorUserId, $origin): PaymentAllocation {
            if ($existing = PaymentAllocation::query()->where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($lockedPayment->status !== PaymentStatus::SUCCEEDED) {
                throw new DomainException('Only a succeeded Payment may be allocated.');
            }
            if (! in_array($lockedInvoice->status, [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID], true)) {
                throw new DomainException('Invoice does not accept settlement.');
            }
            if ($lockedPayment->billing_account_id !== $lockedInvoice->billing_account_id || $lockedPayment->currency !== $lockedInvoice->currency) {
                throw new DomainException('Payment and Invoice payer/currency must match.');
            }
            $available = $lockedPayment->amount_minor - (int) PaymentAllocation::query()->where('payment_id', $lockedPayment->id)->sum('amount_minor');
            if ($amountMinor > $available || $amountMinor > $lockedInvoice->amount_due_minor) {
                throw new DomainException('Allocation exceeds available Payment or Invoice due.');
            }
            $allocation = PaymentAllocation::query()->create(['payment_id' => $lockedPayment->id, 'invoice_id' => $lockedInvoice->id, 'amount_minor' => $amountMinor, 'allocated_at' => now(), 'actor_user_id' => $actorUserId, 'origin' => $origin, 'idempotency_key' => $idempotencyKey]);
            $paid = $lockedInvoice->amount_paid_minor + $amountMinor;
            $due = $lockedInvoice->total_minor - $paid;
            $lockedInvoice->forceFill(['amount_paid_minor' => $paid, 'amount_due_minor' => $due, 'status' => $due === 0 ? InvoiceStatus::PAID : InvoiceStatus::PARTIALLY_PAID, 'paid_at' => $due === 0 ? now() : null])->save();
            FinancialAudit::record(CommercialAuditEventType::PAYMENT_ALLOCATED, ['billing_account_id' => $lockedInvoice->billing_account_id, 'invoice_id' => $lockedInvoice->id, 'payment_id' => $lockedPayment->id], $actorUserId, null, ['amount_minor' => $amountMinor, 'origin' => $origin]);

            return $allocation;
        });
    }
}

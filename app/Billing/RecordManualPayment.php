<?php

namespace App\Billing;

use App\Enums\CommercialAuditEventType;
use App\Enums\PaymentSource;
use App\Enums\PaymentStatus;
use App\Models\BillingAccount;
use App\Models\Payment;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class RecordManualPayment
{
    public function execute(BillingAccount $payer, int $amountMinor, string $currency, DateTimeInterface $receivedAt, string $institution, string $reference, string $idempotencyKey, ?int $actorUserId, string $origin): Payment
    {
        $currency = strtoupper($currency);
        if ($amountMinor <= 0 || $origin === '' || trim($institution) === '' || trim($reference) === '' || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Manual Payment evidence is incomplete.');
        }

        return DB::transaction(function () use ($payer, $amountMinor, $currency, $receivedAt, $institution, $reference, $idempotencyKey, $actorUserId, $origin): Payment {
            if ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }
            $payment = Payment::query()->create(['billing_account_id' => $payer->id, 'status' => PaymentStatus::SUCCEEDED, 'source' => PaymentSource::MANUAL_BANK_TRANSFER, 'currency' => $currency, 'amount_minor' => $amountMinor, 'received_at' => $receivedAt, 'idempotency_key' => $idempotencyKey, 'manual_institution' => trim($institution), 'manual_reference' => trim($reference)]);
            FinancialAudit::record(CommercialAuditEventType::PAYMENT_RECORDED, ['billing_account_id' => $payer->id, 'payment_id' => $payment->id], $actorUserId, null, ['source' => 'manual_bank_transfer', 'amount_minor' => $amountMinor, 'currency' => $currency, 'origin' => $origin]);
            FinancialAudit::record(CommercialAuditEventType::PAYMENT_SUCCEEDED, ['billing_account_id' => $payer->id, 'payment_id' => $payment->id], $actorUserId, ['status' => 'pending'], ['status' => 'succeeded', 'origin' => $origin]);

            return $payment;
        });
    }
}

<?php

namespace App\Billing\Providers;

use App\Billing\ConvertTrialAfterSettlement;
use App\Billing\FinancialAudit;
use App\Billing\RenewSubscription;
use App\Billing\SettleInvoice;
use App\Enums\CheckoutIntentStatus;
use App\Enums\CommercialAuditEventType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentProviderReferenceType;
use App\Enums\PaymentSource;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\CheckoutIntent;
use App\Models\Payment;
use App\Models\PaymentProviderReference;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReconcileProviderPayment
{
    public function __construct(private readonly SettleInvoice $settle, private readonly ConvertTrialAfterSettlement $convertTrial, private readonly RenewSubscription $renew) {}

    public function reconcile(NormalizedPaymentEvidence $evidence): Payment
    {
        if ($evidence->provider !== 'paystack' || $evidence->providerAccount !== config('billing.providers.paystack.account_key') || $evidence->environment !== 'test') {
            throw new DomainException('Provider account or environment mismatch.');
        }
        if ($evidence->status !== 'success') {
            throw new DomainException('Provider transaction is not successful.');
        }
        $intent = CheckoutIntent::query()->with(['invoice.lines.subscription.item'])->where('local_reference', $evidence->localReference)->first();
        if (! $intent) {
            throw new DomainException('Provider reference is not bound to a checkout intent.');
        }
        $invoice = $intent->invoice;
        if ($intent->provider !== $evidence->provider || $intent->provider_account_key !== $evidence->providerAccount || $intent->provider_environment !== $evidence->environment || $intent->provider_reference !== $evidence->providerTransactionReference) {
            throw new DomainException('Provider evidence is not bound to this checkout.');
        }
        if ($intent->billing_account_id !== $invoice->billing_account_id || $intent->amount_minor !== $evidence->amountMinor || $invoice->amount_due_minor !== $evidence->amountMinor || $intent->currency !== $evidence->currency || $invoice->currency !== $evidence->currency) {
            throw new DomainException('Provider amount, currency, payer, or Invoice due mismatch.');
        }
        if (! in_array($invoice->status, [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID], true)) {
            $existing = Payment::query()->where('idempotency_key', $this->paymentKey($evidence))->first();
            if ($invoice->status === InvoiceStatus::PAID && $existing) {
                return $existing;
            }
            throw new DomainException('Invoice does not accept provider settlement.');
        }
        $payment = DB::transaction(function () use ($intent, $evidence): Payment {
            if ($existing = Payment::query()->where('idempotency_key', $this->paymentKey($evidence))->first()) {
                return $existing;
            }
            $payment = Payment::query()->create(['billing_account_id' => $intent->billing_account_id, 'status' => PaymentStatus::SUCCEEDED, 'source' => PaymentSource::PROVIDER, 'currency' => $evidence->currency, 'amount_minor' => $evidence->amountMinor, 'received_at' => $evidence->occurredAt, 'idempotency_key' => $this->paymentKey($evidence)]);
            PaymentProviderReference::query()->create(['provider' => $evidence->provider, 'provider_account_key' => $evidence->providerAccount, 'reference_type' => PaymentProviderReferenceType::PAYMENT, 'provider_reference' => $evidence->providerTransactionId, 'local_type' => Payment::class, 'local_id' => $payment->id]);
            FinancialAudit::record(CommercialAuditEventType::PAYMENT_RECORDED, ['billing_account_id' => $payment->billing_account_id, 'payment_id' => $payment->id], null, null, ['source' => 'provider', 'provider' => $evidence->provider, 'provider_account' => $evidence->providerAccount]);
            FinancialAudit::record(CommercialAuditEventType::PAYMENT_SUCCEEDED, ['billing_account_id' => $payment->billing_account_id, 'payment_id' => $payment->id], null, ['status' => 'pending'], ['status' => 'succeeded', 'provider' => $evidence->provider]);

            return $payment;
        });
        if ($invoice->fresh()->status !== InvoiceStatus::PAID) {
            $this->settle->execute($payment, $invoice->fresh(), $evidence->amountMinor, 'provider-allocation-'.$evidence->provider.'-'.$evidence->providerTransactionId, null, 'verified_provider_payment');
        }
        $invoice = $invoice->fresh('lines.subscription');
        $subscriptions = $invoice->lines->pluck('subscription')->unique('id');
        if ($subscriptions->count() === 1) {
            $subscription = $subscriptions->first();
            if ($subscription->status === SubscriptionStatus::TRIALING) {
                $this->convertTrial->execute($subscription, $invoice, 'provider-trial-'.$evidence->providerTransactionId, null, 'verified_provider_payment');
            } elseif ($subscription->status === SubscriptionStatus::ACTIVE) {
                $this->renew->execute($subscription, $invoice, 'provider-renewal-'.$evidence->providerTransactionId, null, 'verified_provider_payment');
            }
        }
        $intent->forceFill(['status' => CheckoutIntentStatus::RECONCILED])->save();

        return $payment->fresh('allocations');
    }

    private function paymentKey(NormalizedPaymentEvidence $evidence): string
    {
        return 'provider-'.$evidence->provider.'-'.$evidence->providerAccount.'-'.$evidence->providerTransactionId;
    }
}

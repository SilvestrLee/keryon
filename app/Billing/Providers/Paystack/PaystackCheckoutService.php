<?php

namespace App\Billing\Providers\Paystack;

use App\Enums\CheckoutIntentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentProviderReferenceType;
use App\Models\CheckoutIntent;
use App\Models\Invoice;
use App\Models\PaymentProviderReference;
use DomainException;
use Illuminate\Support\Str;

class PaystackCheckoutService
{
    public function __construct(private readonly PaystackPaymentGateway $gateway) {}

    public function initialize(Invoice $invoice, ?string $callbackUrl = null): CheckoutIntent
    {
        $invoice->loadMissing('billingAccount.billingProfile');
        if (! in_array($invoice->status, [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID], true) || $invoice->amount_due_minor <= 0) {
            throw new DomainException('Paystack checkout requires an issued Invoice with an amount due.');
        }
        $email = $invoice->billingAccount->billingProfile?->billing_email;
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Paystack checkout requires a valid billing email.');
        }
        $config = $this->gateway->configuration();
        $intent = CheckoutIntent::query()->firstOrCreate(
            ['invoice_id' => $invoice->id, 'provider' => 'paystack', 'provider_account_key' => $config['account_key']],
            ['billing_account_id' => $invoice->billing_account_id, 'provider_environment' => 'test', 'local_reference' => 'ky_test_'.Str::lower(Str::random(40)), 'amount_minor' => $invoice->amount_due_minor, 'currency' => $invoice->currency, 'status' => CheckoutIntentStatus::PREPARED]
        );
        if ($intent->status === CheckoutIntentStatus::INITIALIZED) {
            return $intent;
        }
        if ($intent->amount_minor !== $invoice->amount_due_minor || $intent->currency !== $invoice->currency) {
            throw new DomainException('Prepared checkout no longer matches the Invoice obligation.');
        }
        $payload = ['email' => $email, 'amount' => $intent->amount_minor, 'currency' => $intent->currency, 'reference' => $intent->local_reference, 'metadata' => ['keryon_invoice_uuid' => $invoice->uuid]];
        if ($callbackUrl !== null) {
            $payload['callback_url'] = $callbackUrl;
        }
        $data = $this->gateway->initialize($payload);
        if (($data['reference'] ?? null) !== $intent->local_reference || ! isset($data['authorization_url'], $data['access_code'])) {
            throw new DomainException('Paystack initialization returned an unbound reference.');
        }
        $intent->forceFill(['status' => CheckoutIntentStatus::INITIALIZED, 'provider_reference' => $data['reference'], 'authorization_url' => $data['authorization_url'], 'access_code' => $data['access_code']])->save();
        PaymentProviderReference::query()->firstOrCreate(['provider' => 'paystack', 'provider_account_key' => $config['account_key'], 'reference_type' => PaymentProviderReferenceType::CHECKOUT_SESSION, 'local_type' => CheckoutIntent::class, 'local_id' => $intent->id], ['provider_reference' => $data['reference']]);

        return $intent;
    }
}

<?php

namespace App\Billing\Providers\Paystack;

use App\Billing\Providers\ReconcileProviderPayment;
use App\Billing\ProviderWebhookReceiptService;
use App\Enums\ProviderWebhookReceiptStatus;
use App\Models\ProviderWebhookReceipt;
use DomainException;
use Illuminate\Support\Facades\Log;
use JsonException;

class PaystackWebhookProcessor
{
    public function __construct(private readonly PaystackSignatureVerifier $signatures, private readonly ProviderWebhookReceiptService $receipts, private readonly PaystackPaymentGateway $gateway, private readonly PaystackPaymentNormalizer $normalizer, private readonly ReconcileProviderPayment $reconciler) {}

    public function process(string $rawBody, ?string $signature, ?string $sourceIp): array
    {
        if (! $this->signatures->valid($rawBody, $signature)) {
            return ['accepted' => false, 'status' => 401];
        }
        $config = $this->gateway->configuration();
        if ($config['enforce_webhook_ips'] && ! in_array($sourceIp, $config['webhook_ips'], true)) {
            return ['accepted' => false, 'status' => 403];
        }
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['accepted' => false, 'status' => 400];
        }
        $event = is_string($payload['event'] ?? null) ? $payload['event'] : 'unknown';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $identity = hash('sha256', $event.'|'.($data['id'] ?? '').'|'.($data['reference'] ?? '').'|'.hash('sha256', $rawBody));
        $receipt = $this->receipts->receive('paystack', $config['account_key'], $identity, $event, hash('sha256', $rawBody), isset($data['reference']) ? (string) $data['reference'] : null);
        if (in_array($receipt->status, [ProviderWebhookReceiptStatus::PROCESSED, ProviderWebhookReceiptStatus::IGNORED], true)) {
            return ['accepted' => true, 'status' => 200, 'receipt_id' => $receipt->id];
        }
        if ($event !== 'charge.success') {
            if ($receipt->status === ProviderWebhookReceiptStatus::RECEIVED) {
                $receipt->transition(ProviderWebhookReceiptStatus::IGNORED);
            }

return ['accepted' => true, 'status' => 200, 'receipt_id' => $receipt->id];
        }
        if (! isset($data['reference'])) {
            return $this->fail($receipt, 'missing_reference');
        }
        try {
            if ($receipt->status !== ProviderWebhookReceiptStatus::PROCESSING) {
                $receipt->transition(ProviderWebhookReceiptStatus::PROCESSING);
            }
            $verified = $this->gateway->verify((string) $data['reference']);
            $evidence = $this->normalizer->normalize($verified, $receipt->payload_hash);
            $payment = $this->reconciler->reconcile($evidence);
            $receipt->transition(ProviderWebhookReceiptStatus::PROCESSED);
            Log::info('Provider payment reconciled.', ['provider' => 'paystack', 'provider_account' => $config['account_key'], 'local_reference' => $evidence->localReference, 'receipt_id' => $receipt->id, 'provider_transaction_reference' => $evidence->providerTransactionReference, 'payment_id' => $payment->id, 'outcome' => 'processed']);

            return ['accepted' => true, 'status' => 200, 'receipt_id' => $receipt->id];
        } catch (DomainException $exception) {
            return $this->fail($receipt, 'financial_mismatch');
        } catch (\Throwable $exception) {
            return $this->fail($receipt, 'provider_or_reconciliation_failure', 503);
        }
    }

    private function fail(ProviderWebhookReceipt $receipt, string $category, int $status = 200): array
    {
        if ($receipt->status === ProviderWebhookReceiptStatus::RECEIVED) {
            $receipt->transition(ProviderWebhookReceiptStatus::PROCESSING);
        }
        if ($receipt->status === ProviderWebhookReceiptStatus::PROCESSING) {
            $receipt->last_failure_category = $category;
            $receipt->save();
            $receipt->transition(ProviderWebhookReceiptStatus::FAILED);
        }
        Log::warning('Provider payment reconciliation did not complete.', ['provider' => 'paystack', 'provider_account' => config('billing.providers.paystack.account_key'), 'receipt_id' => $receipt->id, 'outcome' => $category]);

        return ['accepted' => true, 'status' => $status, 'receipt_id' => $receipt->id];
    }
}

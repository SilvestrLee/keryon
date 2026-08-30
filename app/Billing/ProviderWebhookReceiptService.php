<?php

namespace App\Billing;

use App\Enums\ProviderWebhookReceiptStatus;
use App\Models\ProviderWebhookReceipt;
use DomainException;

class ProviderWebhookReceiptService
{
    public function receive(string $provider, string $accountKey, string $eventId, string $eventType, string $payloadHash, ?string $objectReference = null): ProviderWebhookReceipt
    {
        if ($provider === '' || $accountKey === '' || $eventId === '' || $eventType === '' || ! preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
            throw new DomainException('Webhook receipt evidence is incomplete.');
        }

        return ProviderWebhookReceipt::query()->firstOrCreate(
            ['provider' => $provider, 'provider_account_key' => $accountKey, 'provider_event_id' => $eventId],
            ['provider_event_type' => $eventType, 'status' => ProviderWebhookReceiptStatus::RECEIVED, 'received_at' => now(), 'payload_hash' => $payloadHash, 'bounded_object_reference' => $objectReference]
        );
    }
}

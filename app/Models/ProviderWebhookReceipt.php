<?php

namespace App\Models;

use App\Enums\ProviderWebhookReceiptStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;

class ProviderWebhookReceipt extends Model
{
    protected $fillable = ['provider', 'provider_account_key', 'provider_event_id', 'provider_event_type', 'status', 'received_at', 'processing_started_at', 'processed_at', 'payload_hash', 'bounded_object_reference', 'attempt_count', 'last_failure_category'];

    protected function casts(): array
    {
        return ['status' => ProviderWebhookReceiptStatus::class, 'received_at' => 'datetime', 'processing_started_at' => 'datetime', 'processed_at' => 'datetime', 'attempt_count' => 'integer'];
    }

    public function transition(ProviderWebhookReceiptStatus $to): self
    {
        $allowed = match ($this->status) {
            ProviderWebhookReceiptStatus::RECEIVED => [ProviderWebhookReceiptStatus::PROCESSING, ProviderWebhookReceiptStatus::IGNORED],
            ProviderWebhookReceiptStatus::PROCESSING => [ProviderWebhookReceiptStatus::PROCESSED, ProviderWebhookReceiptStatus::FAILED],
            ProviderWebhookReceiptStatus::FAILED => [ProviderWebhookReceiptStatus::PROCESSING],
            default => [],
        };
        if (! in_array($to, $allowed, true)) {
            throw new DomainException('Invalid webhook receipt transition.');
        }
        $this->status = $to;
        $this->attempt_count += $to === ProviderWebhookReceiptStatus::PROCESSING ? 1 : 0;
        if ($to === ProviderWebhookReceiptStatus::PROCESSING) {
            $this->processing_started_at = now();
        }
        if (in_array($to, [ProviderWebhookReceiptStatus::PROCESSED, ProviderWebhookReceiptStatus::IGNORED], true)) {
            $this->processed_at = now();
        }
        $this->save();

        return $this;
    }
}

<?php

namespace App\Models;

use App\Enums\PolicyGovernanceDeliveryStatus;
use App\Enums\PolicyGovernanceTransitionType;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Durable, append-only governance evidence for a PolicyVersion lifecycle
 * transition (K-LEGAL-001B-A-DECISION-ADDENDUM.md). Identity/content fields
 * are permanent from the moment of creation; only the delivery-tracking
 * fields may ever change, and only along the single permitted
 * pending -> delivered path. No row is ever deleted.
 *
 * This table intentionally never stores a legal-document body — only a
 * SHA-256 snapshot of the content the transition applied to.
 */
class PolicyGovernanceEvent extends Model
{
    protected $fillable = [
        'event_uuid', 'document_type', 'version', 'transition_type', 'content_hash',
        'actor_reference', 'occurred_at', 'delivery_status', 'delivery_attempts',
        'last_attempted_at', 'delivered_at', 'external_acknowledgement_reference',
    ];

    protected function casts(): array
    {
        return [
            'transition_type' => PolicyGovernanceTransitionType::class,
            'delivery_status' => PolicyGovernanceDeliveryStatus::class,
            'occurred_at' => 'immutable_datetime',
            'last_attempted_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    public static function record(
        string $documentType,
        string $version,
        PolicyGovernanceTransitionType $transitionType,
        string $contentHash,
        string $actorReference,
    ): self {
        if (strlen($contentHash) !== 64) {
            throw new DomainException('A PolicyGovernanceEvent content hash must be a 64-character SHA-256 hex digest.');
        }
        if (blank($documentType) || blank($version) || blank($actorReference)) {
            throw new DomainException('PolicyGovernanceEvent requires non-blank document type, version, and actor reference.');
        }

        return static::create([
            'event_uuid' => (string) Str::uuid(),
            'document_type' => $documentType,
            'version' => $version,
            'transition_type' => $transitionType->value,
            'content_hash' => $contentHash,
            'actor_reference' => $actorReference,
            'occurred_at' => now(),
            'delivery_status' => PolicyGovernanceDeliveryStatus::PENDING->value,
            'delivery_attempts' => 0,
        ]);
    }

    protected static function booted(): void
    {
        static::updating(function (self $event): void {
            $immutable = ['event_uuid', 'document_type', 'version', 'transition_type', 'content_hash', 'actor_reference', 'occurred_at'];
            if ($event->isDirty($immutable)) {
                throw new DomainException('A PolicyGovernanceEvent\'s identity and content fields are immutable once recorded.');
            }

            if ($event->isDirty('delivery_status')) {
                $original = PolicyGovernanceDeliveryStatus::tryFrom((string) $event->getRawOriginal('delivery_status'));
                $newStatus = $event->delivery_status instanceof PolicyGovernanceDeliveryStatus ? $event->delivery_status->value : $event->delivery_status;
                if ($original !== PolicyGovernanceDeliveryStatus::PENDING || $newStatus !== PolicyGovernanceDeliveryStatus::DELIVERED->value) {
                    throw new DomainException('delivery_status may only transition from pending to delivered.');
                }
                if (blank($event->external_acknowledgement_reference) || $event->delivered_at === null) {
                    throw new DomainException('Marking an event delivered requires an acknowledgement reference and a delivered_at timestamp in the same write.');
                }
            } elseif ($event->isDirty(['external_acknowledgement_reference', 'delivered_at'])) {
                throw new DomainException('Acknowledgement fields may only change together with a pending-to-delivered transition.');
            }
        });

        static::deleting(function (self $event): void {
            throw new DomainException('PolicyGovernanceEvent rows are permanent and may never be deleted.');
        });
    }
}

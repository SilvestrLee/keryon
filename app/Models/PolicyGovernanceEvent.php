<?php

namespace App\Models;

use App\Enums\PolicyGovernanceDeliveryStatus;
use App\Enums\PolicyGovernanceTransitionType;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Durable, append-only governance evidence for a PolicyVersion lifecycle
 * transition (K-LEGAL-001B-A-DECISION-ADDENDUM.md). Every row must be BORN
 * pending, with zero attempts and no delivery timestamps/acknowledgement —
 * enforced on creation, not only assumed of the record() factory. Identity
 * and content fields are permanent from the moment of creation. Once
 * delivered, the entire delivery history — including attempts and
 * timestamps, not only the terminal fields — is frozen. No row is ever
 * deleted.
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
        // Validation lives in the creating() guard below, not here, so that
        // ANY creation path — not only this factory — is protected. This
        // method is a convenience builder, not the source of safety.
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
        static::creating(function (self $event): void {
            if (blank($event->event_uuid) || blank($event->document_type) || blank($event->version) || blank($event->actor_reference)) {
                throw new DomainException('PolicyGovernanceEvent requires a non-blank event_uuid, document type, version, and actor reference.');
            }
            if ($event->transition_type === null || $event->occurred_at === null) {
                throw new DomainException('PolicyGovernanceEvent requires a transition type and an occurred_at timestamp.');
            }
            $hash = $event->content_hash;
            if (! is_string($hash) || preg_match('/^[0-9a-f]{64}$/i', $hash) !== 1) {
                throw new DomainException('PolicyGovernanceEvent content_hash must be a 64-character hexadecimal SHA-256 digest.');
            }

            // A PolicyGovernanceEvent may only ever be BORN pending, with a
            // clean delivery-tracking slate — this closes the path where a
            // caller bypasses record() and constructs an already-"delivered"
            // event directly, which would otherwise fabricate evidence of a
            // delivery that never actually happened.
            $status = $event->delivery_status instanceof PolicyGovernanceDeliveryStatus ? $event->delivery_status->value : $event->delivery_status;
            if (($status ?? PolicyGovernanceDeliveryStatus::PENDING->value) !== PolicyGovernanceDeliveryStatus::PENDING->value) {
                throw new DomainException('A PolicyGovernanceEvent must be created in the pending delivery state.');
            }
            if (($event->delivery_attempts ?? 0) !== 0) {
                throw new DomainException('A PolicyGovernanceEvent must be created with zero delivery attempts.');
            }
            if ($event->last_attempted_at !== null || $event->delivered_at !== null) {
                throw new DomainException('A PolicyGovernanceEvent must be created with no delivery timestamps set.');
            }
            if ($event->external_acknowledgement_reference !== null) {
                throw new DomainException('A PolicyGovernanceEvent must be created with no acknowledgement reference.');
            }
        });

        static::updating(function (self $event): void {
            $originalStatus = PolicyGovernanceDeliveryStatus::tryFrom((string) $event->getRawOriginal('delivery_status'));

            // Once delivered, the entire delivery history — including
            // attempts and timestamps, not only the terminal fields
            // themselves — is frozen. This must be checked before the
            // pending-to-delivered branch below, which only ever applies
            // while the original status is still pending.
            if ($originalStatus === PolicyGovernanceDeliveryStatus::DELIVERED
                && $event->isDirty(['delivery_status', 'delivery_attempts', 'last_attempted_at', 'external_acknowledgement_reference', 'delivered_at'])) {
                throw new DomainException('A delivered PolicyGovernanceEvent\'s delivery history is frozen and may never change again.');
            }

            $immutable = ['event_uuid', 'document_type', 'version', 'transition_type', 'content_hash', 'actor_reference', 'occurred_at'];
            if ($event->isDirty($immutable)) {
                throw new DomainException('A PolicyGovernanceEvent\'s identity and content fields are immutable once recorded.');
            }

            if ($event->isDirty('delivery_status')) {
                $newStatus = $event->delivery_status instanceof PolicyGovernanceDeliveryStatus ? $event->delivery_status->value : $event->delivery_status;
                if ($originalStatus !== PolicyGovernanceDeliveryStatus::PENDING || $newStatus !== PolicyGovernanceDeliveryStatus::DELIVERED->value) {
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

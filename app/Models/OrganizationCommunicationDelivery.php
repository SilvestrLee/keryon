<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Enums\OrganizationCommunicationDeliveryState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * K-ORG-COMMS-001C §18-§21 / K-ORG-COMMS-001D §11/§48 — one durable,
 * Organization-owned, Church-addressed delivery row. This IS the
 * historical recipient snapshot evidence — deliberately not
 * `BelongsToChurch` (§19): it is Organization Communications distribution
 * evidence addressed to a Church, not a Church-owned tenant record.
 *
 * K-ORG-COMMS-001D adds deliberate Church response evidence
 * (`accepted_at`/`declined_at`/`decline_reason_code`/
 * `responded_by_church_membership_id`) as the *only* fields this model
 * ever allows to mutate after creation — every identity/distribution/
 * snapshot/availability column remains exactly as immutable as 001C left
 * it (§48, a critical invariant). `state` itself is never rewritten by a
 * Church response — Accepted/Declined/Expired are *derived* display
 * states (§10/§11), computed by `derivedResponseState()`, not persisted
 * as a `state` transition.
 */
class OrganizationCommunicationDelivery extends Model
{
    private const RESPONSE_FIELDS = [
        'accepted_at',
        'declined_at',
        'decline_reason_code',
        'responded_by_church_membership_id',
    ];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'state' => OrganizationCommunicationDeliveryState::class,
            'available_at' => 'datetime',
            'available_until' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'decline_reason_code' => OrganizationCommunicationDeclineReasonCode::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $delivery): void {
            $delivery->uuid ??= (string) Str::uuid();
            $delivery->state ??= OrganizationCommunicationDeliveryState::AVAILABLE;
            $delivery->assertOwnershipChain();
        });

        static::updating(function (self $delivery): void {
            // `isDirty([])` is not "nothing is dirty" — Laravel treats an
            // empty filter array as "check everything", which would make
            // this throw on every legitimate response-only save. Count
            // the non-response-field diff directly instead.
            $nonResponseFieldsDirty = array_diff(array_keys($delivery->getDirty()), self::RESPONSE_FIELDS);
            if ($nonResponseFieldsDirty !== []) {
                throw new LogicException('Organization communication delivery evidence is immutable except for the Church response fields.');
            }

            if ($delivery->accepted_at !== null && $delivery->declined_at !== null) {
                throw new LogicException('A delivery cannot be both Accepted and Declined.');
            }

            $original = $delivery->getRawOriginal();
            $wasResponded = filled($original['accepted_at'] ?? null) || filled($original['declined_at'] ?? null);
            if ($wasResponded && ($delivery->isDirty('accepted_at') || $delivery->isDirty('declined_at'))) {
                throw new LogicException('A Church response is terminal and cannot be changed once recorded.');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationDistribution::class, 'organization_communication_distribution_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationRevision::class, 'organization_communication_revision_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ChurchOrganizationAssignment::class, 'church_organization_assignment_id');
    }

    public function responderMembership(): BelongsTo
    {
        return $this->belongsTo(ChurchMembership::class, 'responded_by_church_membership_id');
    }

    /**
     * K-ORG-COMMS-001D §10/§11 — the single source of truth for display
     * state. Priority: an explicit Church response is always final and
     * wins over expiry (§34: accepted-before-expiry never becomes
     * Expired); Organization withdrawal (rendered read-only, never set by
     * 001D) beats an unresponded, time-based Expired; Expired only
     * applies to a still-unresponded delivery whose availability window
     * has passed; otherwise Available.
     */
    public function derivedResponseState(): OrganizationCommunicationDeliveryState|string
    {
        if ($this->declined_at !== null) {
            return 'declined';
        }

        if ($this->accepted_at !== null) {
            return 'accepted';
        }

        if ($this->state === OrganizationCommunicationDeliveryState::WITHDRAWN) {
            return 'withdrawn';
        }

        if ($this->available_until !== null && $this->available_until->isPast()) {
            return 'expired';
        }

        return 'available';
    }

    private function assertOwnershipChain(): void
    {
        $distribution = OrganizationCommunicationDistribution::query()->find($this->organization_communication_distribution_id);

        if ($distribution === null
            || $distribution->organization_communication_revision_id !== $this->organization_communication_revision_id
            || (int) $distribution->organization_id !== (int) $this->organization_id) {
            throw new LogicException('Organization communication delivery must reference its own distribution\'s Organization and revision.');
        }
    }
}

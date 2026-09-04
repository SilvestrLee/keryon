<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationDistributionState;
use App\Enums\OrganizationCommunicationTargetMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * K-ORG-COMMS-001C §9 — the durable distribution aggregate. Identifies
 * what was distributed (immutable revision), by whom, under what
 * governing scope and deliberate target selection, and its bounded
 * processing lifecycle. Never stores duplicated communication body or
 * asset bytes (§9, §40) — the historical audience lives on
 * `OrganizationCommunicationDelivery` rows, not here (§21/§22).
 */
class OrganizationCommunicationDistribution extends Model
{
    private const IMMUTABLE_FIELDS = [
        'uuid',
        'organization_id',
        'organization_communication_id',
        'organization_communication_revision_id',
        'governing_unit_id',
        'initiated_by_organization_membership_id',
        'target_mode',
        'target_unit_id',
        'target_church_ids',
        'target_definition_hash',
        'requested_at',
    ];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'state' => OrganizationCommunicationDistributionState::class,
            'target_mode' => OrganizationCommunicationTargetMode::class,
            'target_church_ids' => 'array',
            'requested_at' => 'datetime',
            'snapshot_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'resolved_recipient_count' => 'integer',
            'delivered_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $distribution): void {
            $distribution->uuid ??= (string) Str::uuid();
            $distribution->state ??= OrganizationCommunicationDistributionState::PENDING;
            $distribution->requested_at ??= now();
            $distribution->assertOwnershipChain();
        });

        static::updating(function (self $distribution): void {
            if ($distribution->isDirty(self::IMMUTABLE_FIELDS)) {
                throw new LogicException('Organization communication distribution identity and target are immutable.');
            }

            if ($distribution->isDirty('state')) {
                $from = OrganizationCommunicationDistributionState::from((string) $distribution->getRawOriginal('state'));
                $allowed = match ($from) {
                    OrganizationCommunicationDistributionState::PENDING => [
                        OrganizationCommunicationDistributionState::PROCESSING,
                        OrganizationCommunicationDistributionState::FAILED,
                    ],
                    OrganizationCommunicationDistributionState::PROCESSING => [
                        OrganizationCommunicationDistributionState::COMPLETED,
                        OrganizationCommunicationDistributionState::FAILED,
                    ],
                    OrganizationCommunicationDistributionState::COMPLETED,
                    OrganizationCommunicationDistributionState::FAILED => [],
                };

                if (! in_array($distribution->state, $allowed, true)) {
                    throw new LogicException("Organization communication distribution cannot transition from [{$from->value}] to [{$distribution->state->value}].");
                }
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunication::class, 'organization_communication_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationRevision::class, 'organization_communication_revision_id');
    }

    public function governingUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'governing_unit_id');
    }

    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'target_unit_id');
    }

    public function initiatorMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'initiated_by_organization_membership_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(OrganizationCommunicationDelivery::class, 'organization_communication_distribution_id');
    }

    private function assertOwnershipChain(): void
    {
        $communication = OrganizationCommunication::query()->find($this->organization_communication_id);
        $revision = OrganizationCommunicationRevision::query()->find($this->organization_communication_revision_id);
        $unitOrganizationId = OrganizationUnit::query()->whereKey($this->governing_unit_id)->value('organization_id');
        $initiatorOrganizationId = OrganizationMembership::query()
            ->whereKey($this->initiated_by_organization_membership_id)
            ->value('organization_id');

        if ($communication === null
            || $revision === null
            || $revision->organization_communication_id !== $communication->id
            || $unitOrganizationId === null
            || $initiatorOrganizationId === null
            || (int) $communication->organization_id !== (int) $this->organization_id
            || (int) $unitOrganizationId !== (int) $this->organization_id
            || (int) $initiatorOrganizationId !== (int) $this->organization_id) {
            throw new LogicException('Organization communication distribution ownership, communication, revision, governing Unit, and initiator must belong to the same Organization.');
        }

        if ($this->target_unit_id !== null) {
            $targetUnitOrganizationId = OrganizationUnit::query()->whereKey($this->target_unit_id)->value('organization_id');
            if ((int) $targetUnitOrganizationId !== (int) $this->organization_id) {
                throw new LogicException('Organization communication distribution target Unit must belong to the same Organization.');
            }
        }
    }
}

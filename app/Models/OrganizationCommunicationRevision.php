<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationRevisionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use LogicException;

class OrganizationCommunicationRevision extends Model
{
    public const CANONICAL_FIELDS = [
        'title',
        'summary',
        'requested_action',
        'adaptation_policy',
        'campaign_starts_on',
        'campaign_ends_on',
        'recommended_response_on',
        'suggested_publish_by',
        'available_from',
        'available_until',
    ];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'state' => OrganizationCommunicationRevisionState::class,
            'adaptation_policy' => OrganizationCommunicationAdaptationPolicy::class,
            'campaign_starts_on' => 'date',
            'campaign_ends_on' => 'date',
            'recommended_response_on' => 'date',
            'suggested_publish_by' => 'date',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'submitted_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            $revision->state ??= OrganizationCommunicationRevisionState::DRAFT;
            $revision->adaptation_policy ??= OrganizationCommunicationAdaptationPolicy::LOCAL_ADAPTATION_ENCOURAGED;
            $revision->assertOwnershipChain();
            $revision->assertDates();
        });

        static::updating(function (self $revision): void {
            if ($revision->isDirty([
                'organization_communication_id',
                'created_by_organization_membership_id',
                'version',
            ])) {
                throw new LogicException('Organization communication revision identity is immutable.');
            }

            $originalState = OrganizationCommunicationRevisionState::from((string) $revision->getRawOriginal('state'));
            if ($revision->isDirty('state')) {
                $allowed = match ($originalState) {
                    OrganizationCommunicationRevisionState::DRAFT => [OrganizationCommunicationRevisionState::IN_REVIEW],
                    OrganizationCommunicationRevisionState::IN_REVIEW => [
                        OrganizationCommunicationRevisionState::CHANGES_REQUESTED,
                        OrganizationCommunicationRevisionState::APPROVED,
                    ],
                    OrganizationCommunicationRevisionState::CHANGES_REQUESTED => [OrganizationCommunicationRevisionState::DRAFT],
                    OrganizationCommunicationRevisionState::APPROVED => [OrganizationCommunicationRevisionState::DISTRIBUTED],
                    OrganizationCommunicationRevisionState::DISTRIBUTED => [],
                };

                if (! in_array($revision->state, $allowed, true)) {
                    throw new LogicException("Organization communication revision cannot transition from [{$originalState->value}] to [{$revision->state->value}].");
                }
            }

            if (in_array($originalState, [
                OrganizationCommunicationRevisionState::APPROVED,
                OrganizationCommunicationRevisionState::DISTRIBUTED,
            ], true) && $revision->isDirty(self::CANONICAL_FIELDS)) {
                throw new LogicException('Approved or distributed Organization communication revisions are immutable.');
            }

            $revision->assertOwnershipChain();
            $revision->assertDates();
        });
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunication::class, 'organization_communication_id');
    }

    public function creatorMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'created_by_organization_membership_id');
    }

    public function submitterMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'submitted_by_organization_membership_id');
    }

    public function reviewerMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'reviewed_by_organization_membership_id');
    }

    public function approverMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'approved_by_organization_membership_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(OrganizationCommunicationMaterial::class)->orderBy('sort_order');
    }

    public function isSubstantivelyImmutable(): bool
    {
        return in_array($this->state, [
            OrganizationCommunicationRevisionState::APPROVED,
            OrganizationCommunicationRevisionState::DISTRIBUTED,
        ], true);
    }

    /** @return array<string, mixed> */
    public function canonicalAttributes(): array
    {
        return $this->only(self::CANONICAL_FIELDS);
    }

    private function assertOwnershipChain(): void
    {
        $organizationId = OrganizationCommunication::query()
            ->whereKey($this->organization_communication_id)
            ->value('organization_id');

        if ($organizationId === null) {
            throw new LogicException('Organization communication revision requires an existing communication.');
        }

        foreach ([
            $this->created_by_organization_membership_id,
            $this->submitted_by_organization_membership_id,
            $this->reviewed_by_organization_membership_id,
            $this->approved_by_organization_membership_id,
        ] as $membershipId) {
            if ($membershipId === null) {
                continue;
            }

            $membershipOrganizationId = OrganizationMembership::query()->whereKey($membershipId)->value('organization_id');
            if ($membershipOrganizationId === null || (int) $membershipOrganizationId !== (int) $organizationId) {
                throw new LogicException('Revision actors must belong to the communication Organization.');
            }
        }
    }

    private function assertDates(): void
    {
        if ($this->campaign_starts_on !== null
            && $this->campaign_ends_on !== null
            && $this->campaign_ends_on->lt($this->campaign_starts_on)) {
            throw ValidationException::withMessages([
                'campaign_ends_on' => 'Campaign guidance must end on or after it starts.',
            ]);
        }

        if ($this->available_from !== null
            && $this->available_until !== null
            && $this->available_until->lt($this->available_from)) {
            throw ValidationException::withMessages([
                'available_until' => 'Availability must end on or after it starts.',
            ]);
        }
    }
}

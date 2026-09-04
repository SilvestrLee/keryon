<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LogicException;

class OrganizationCommunication extends Model
{
    use SoftDeletes;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'kind' => OrganizationCommunicationKind::class,
            'state' => OrganizationCommunicationState::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $communication): void {
            $communication->uuid ??= (string) Str::uuid();
            $communication->state ??= OrganizationCommunicationState::DRAFT;
            $communication->assertOwnershipChain();
        });

        static::updating(function (self $communication): void {
            if ($communication->isDirty([
                'organization_id',
                'governing_unit_id',
                'created_by_organization_membership_id',
                'kind',
                'uuid',
            ])) {
                throw new LogicException('Organization communication identity and governing scope are immutable.');
            }

            if ($communication->isDirty('state')) {
                $from = OrganizationCommunicationState::from((string) $communication->getRawOriginal('state'));
                $allowed = match ($from) {
                    // K-ORG-COMMS-001C §12 — Active becomes legitimate now
                    // that real distribution evidence exists. Only the
                    // distribution worker sets this, and only after a
                    // completed durable distribution (never merely because
                    // an operator opened the targeting screen).
                    OrganizationCommunicationState::DRAFT => [OrganizationCommunicationState::ACTIVE, OrganizationCommunicationState::CLOSED],
                    OrganizationCommunicationState::ACTIVE => [OrganizationCommunicationState::WITHDRAWN, OrganizationCommunicationState::CLOSED],
                    OrganizationCommunicationState::WITHDRAWN => [OrganizationCommunicationState::CLOSED],
                    OrganizationCommunicationState::CLOSED => [],
                };

                if (! in_array($communication->state, $allowed, true)) {
                    throw new LogicException("Organization communication cannot transition from [{$from->value}] to [{$communication->state->value}].");
                }
            }
        });

        static::deleting(function (self $communication): void {
            if ($communication->isForceDeleting() || $communication->state !== OrganizationCommunicationState::DRAFT) {
                throw new LogicException('Only a never-distributed Draft Organization communication may be deleted.');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function governingUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'governing_unit_id');
    }

    public function creatorMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'created_by_organization_membership_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(OrganizationCommunicationRevision::class)->orderBy('version');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(OrganizationCommunicationDistribution::class, 'organization_communication_id')->latest('id');
    }

    public function latestRevision(): ?OrganizationCommunicationRevision
    {
        return $this->revisions()->latest('version')->first();
    }

    /**
     * Eager-loadable equivalent of latestRevision() — a proper relation
     * (via ofMany) so list queries can `with('currentRevision')` instead
     * of running one query per row.
     */
    public function currentRevision(): HasOne
    {
        return $this->hasOne(OrganizationCommunicationRevision::class)->ofMany('version', 'max');
    }

    private function assertOwnershipChain(): void
    {
        $unitOrganizationId = OrganizationUnit::query()
            ->whereKey($this->governing_unit_id)
            ->value('organization_id');
        $membershipOrganizationId = OrganizationMembership::query()
            ->whereKey($this->created_by_organization_membership_id)
            ->value('organization_id');

        if ($unitOrganizationId === null
            || $membershipOrganizationId === null
            || (int) $unitOrganizationId !== (int) $this->organization_id
            || (int) $membershipOrganizationId !== (int) $this->organization_id) {
            throw new LogicException('Organization communication ownership, governing Unit, and creator membership must belong to the same Organization.');
        }
    }
}

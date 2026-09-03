<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
                    OrganizationCommunicationState::DRAFT => [OrganizationCommunicationState::CLOSED],
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

    public function latestRevision(): ?OrganizationCommunicationRevision
    {
        return $this->revisions()->latest('version')->first();
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

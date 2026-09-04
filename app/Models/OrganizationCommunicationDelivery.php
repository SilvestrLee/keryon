<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationDeliveryState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * K-ORG-COMMS-001C §18-§21 — one durable, Organization-owned,
 * Church-addressed delivery row. This IS the historical recipient
 * snapshot evidence — deliberately not `BelongsToChurch` (§19): it is
 * Organization Communications distribution evidence addressed to a
 * Church, not a Church-owned tenant record.
 */
class OrganizationCommunicationDelivery extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'state' => OrganizationCommunicationDeliveryState::class,
            'available_at' => 'datetime',
            'available_until' => 'datetime',
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
            throw new LogicException('Organization communication delivery evidence is immutable.');
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

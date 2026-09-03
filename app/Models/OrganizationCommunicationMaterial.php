<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationRevisionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrganizationCommunicationMaterial extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['type' => OrganizationCommunicationMaterialType::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $material) => $material->assertDraftRevision());
        static::updating(fn (self $material) => $material->assertDraftRevision());
        static::deleting(fn (self $material) => $material->assertDraftRevision());
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationRevision::class, 'organization_communication_revision_id');
    }

    private function assertDraftRevision(): void
    {
        $state = OrganizationCommunicationRevision::query()
            ->whereKey($this->organization_communication_revision_id)
            ->value('state');

        $state = $state instanceof OrganizationCommunicationRevisionState ? $state->value : $state;

        if ($state !== OrganizationCommunicationRevisionState::DRAFT->value) {
            throw new LogicException('Organization communication materials may only change on a Draft revision.');
        }
    }
}

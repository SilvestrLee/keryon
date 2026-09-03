<?php

namespace App\Models;

use App\Enums\OrganizationCommunicationAssetRightsBasis;
use App\Enums\OrganizationCommunicationRevisionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * K-ORG-COMMS-001B §35-§47 — an Organization-owned communication asset.
 * Belongs unambiguously to Organization -> OrganizationCommunication ->
 * Revision -> Asset. Mutable only while its revision is Draft; once the
 * revision is Approved the asset row becomes immutable, mirroring
 * OrganizationCommunicationMaterial (§82). Never references a Church or
 * a Church MediaAsset.
 */
class OrganizationCommunicationAsset extends Model
{
    use SoftDeletes;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'rights_basis' => OrganizationCommunicationAssetRightsBasis::class,
            'attribution_required' => 'boolean',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->uuid ??= (string) Str::uuid();
            $asset->assertOwnershipChain();
            $asset->assertDraftRevision();
            $asset->assertAttribution();
        });

        static::updating(function (self $asset): void {
            if ($asset->isDirty([
                'organization_id',
                'organization_communication_id',
                'organization_communication_revision_id',
                'uploaded_by_organization_membership_id',
                'uuid',
                'disk',
                'path',
                'sha256',
            ])) {
                throw new LogicException('Organization communication asset identity and stored file are immutable.');
            }

            $asset->assertDraftRevision();
            $asset->assertAttribution();
        });

        static::deleting(function (self $asset): void {
            if ($asset->isForceDeleting()) {
                return;
            }

            $asset->assertDraftRevision();
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

    public function uploaderMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'uploaded_by_organization_membership_id');
    }

    private function assertOwnershipChain(): void
    {
        $communication = OrganizationCommunication::query()->find($this->organization_communication_id);
        $revision = OrganizationCommunicationRevision::query()->find($this->organization_communication_revision_id);
        $uploaderOrganizationId = OrganizationMembership::query()
            ->whereKey($this->uploaded_by_organization_membership_id)
            ->value('organization_id');

        if ($communication === null
            || $revision === null
            || $revision->organization_communication_id !== $communication->id
            || $uploaderOrganizationId === null
            || (int) $communication->organization_id !== (int) $this->organization_id
            || (int) $uploaderOrganizationId !== (int) $this->organization_id) {
            throw new LogicException('Organization communication asset ownership, communication, revision, and uploader must belong to the same Organization.');
        }
    }

    private function assertDraftRevision(): void
    {
        $state = OrganizationCommunicationRevision::query()
            ->whereKey($this->organization_communication_revision_id)
            ->value('state');

        $state = $state instanceof OrganizationCommunicationRevisionState ? $state->value : $state;

        if ($state !== OrganizationCommunicationRevisionState::DRAFT->value) {
            throw new LogicException('Organization communication assets may only change on a Draft revision.');
        }
    }

    private function assertAttribution(): void
    {
        if ($this->attribution_required && ! filled($this->attribution_text)) {
            throw ValidationException::withMessages([
                'attribution_text' => 'Attribution text is required when attribution is marked as required.',
            ]);
        }
    }
}

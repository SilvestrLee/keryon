<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * K-ORG-COMMS-001E §29/§33/§34 — one bounded, explicit lineage row per
 * Church-owned record an import created: exactly one of `content_item_id`
 * / `campaign_id` / `media_asset_id` is set, never a generic polymorphic
 * reference. `organization_communication_material_id` /
 * `organization_communication_asset_id` name the exact source (both
 * nullable — a Campaign-creation row has neither, since a Campaign is
 * sourced from the revision as a whole).
 *
 * Immutable after creation, like its parent import event.
 */
class OrganizationCommunicationImportResult extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::creating(function (self $result): void {
            $destinationsSet = collect([$result->content_item_id, $result->campaign_id, $result->media_asset_id])
                ->filter(fn ($id) => $id !== null)
                ->count();

            if ($destinationsSet !== 1) {
                throw new LogicException('An import result must reference exactly one resulting Church record.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('An Organization communication import result is immutable once recorded.');
        });
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationImport::class, 'organization_communication_import_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class)->withTrashed();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->withTrashed();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationMaterial::class, 'organization_communication_material_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationAsset::class, 'organization_communication_asset_id')->withTrashed();
    }
}

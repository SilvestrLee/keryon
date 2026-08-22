<?php

namespace App\Models\Concerns;

use App\Models\MediaAsset;
use App\Support\TenantContext;
use InvalidArgumentException;

/**
 * K-CHURCHWEB-001B §37 — "media reference cannot cross Church boundary."
 * A foreign key alone only proves the referenced MediaAsset row exists;
 * it says nothing about which Church owns it. Any model that references
 * MediaAsset (ChurchBrandProfile, WebsiteHomeContent,
 * WebsiteLeadershipProfile, WebsiteMinistry) must declare which of its
 * own columns are media references via `mediaAssetForeignKeys()`, and
 * this trait rejects — at save time, before the row is persisted — any
 * reference to a MediaAsset belonging to a different Church.
 *
 * Eloquent fires `saving` *before* `creating` — on a brand-new record,
 * BelongsToChurch's own `creating` hook (which assigns `church_id` from
 * TenantContext) has not run yet by the time this trait's `saving`
 * handler executes, so `$model->church_id` cannot be trusted as "the
 * owning church" during create. Resolve the expected church_id from
 * TenantContext directly instead — the same authoritative source
 * BelongsToChurch itself uses — falling back to the model's own already
 * -persisted `church_id` for the update path (where TenantContext may be
 * unavailable, e.g. a trusted background job re-saving an existing row).
 */
trait ValidatesMediaAssetOwnership
{
    protected static function bootValidatesMediaAssetOwnership(): void
    {
        static::saving(function ($model) {
            $expectedChurchId = app(TenantContext::class)->currentChurchId() ?? $model->church_id;

            foreach ($model->mediaAssetForeignKeys() as $column) {
                $mediaAssetId = $model->{$column};

                if ($mediaAssetId === null) {
                    continue;
                }

                $asset = MediaAsset::withoutGlobalScopes()->find($mediaAssetId);

                if ($asset === null || $asset->church_id !== $expectedChurchId) {
                    throw new InvalidArgumentException(
                        "Media asset [{$mediaAssetId}] does not belong to this Church — cross-Church media reference denied."
                    );
                }
            }
        });
    }

    /**
     * @return list<string> column names on this model that store a
     *                       MediaAsset foreign key
     */
    abstract public function mediaAssetForeignKeys(): array;
}

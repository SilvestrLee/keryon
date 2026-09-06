<?php

namespace App\PublicWebsite;

use App\Enums\MediaRenditionState;
use App\Models\MediaAsset;
use App\Models\MediaRendition;
use Illuminate\Support\Facades\Storage;

class PublicMedia
{
    /**
     * K-WEB-P0-001 §3/§8 — `$churchId` is now a required, explicit
     * constraint, not merely available implicitly through the UUID. Every
     * existing caller (`PublicWebsiteContent::published()`,
     * `ProclaimTheme::renderData()`) already has the publication's/theme's
     * own `$churchId` in scope — this closes the anonymous-resolution
     * defect without ever asking `TenantContext` (which a genuinely
     * anonymous visitor cannot resolve) to authorize anything. See the
     * K-WEB-P0-001 report for the full defect history: `withoutGlobalScopes()`
     * on this method's own `MediaRendition` query never reached the nested
     * `whereHas('publicReferences', ...)` subquery, whose `MediaPublicReference`
     * model applies its own `BelongsToChurch` scope independently — for an
     * anonymous request `TenantContext::currentChurchId()` is null, so that
     * scope failed closed and silently excluded every otherwise-valid
     * active public reference. The fix keeps `BelongsToChurch`/`TenantContext`
     * completely untouched (§14/§15 of the directive) and instead makes the
     * one already-authorized public-publication read explicit end to end:
     * requested Church + rendition UUID + active rendition + active
     * reference + matching Church ownership on *both* the rendition and its
     * reference row must all agree before anything resolves.
     *
     * @return array{url: string, alt: string, width: int|null, height: int|null}|null
     */
    public function rendition(int $churchId, ?string $uuid, ?string $alt = null): ?array
    {
        if ($uuid === null) {
            return null;
        }

        $rendition = MediaRendition::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->where('church_id', $churchId)
            ->where('state', MediaRenditionState::Active->value)
            ->whereHas('publicReferences', function ($query) use ($churchId): void {
                // The nested relationship query is a fresh Builder against
                // `MediaPublicReference`, which independently carries its
                // own `BelongsToChurch` scope — this is the exact scope
                // `withoutGlobalScopes()` above never reached. Removing it
                // by name (not `withoutGlobalScopes()`, which would also
                // silently swallow any future unrelated scope added to this
                // model) and re-asserting Church ownership explicitly here
                // keeps the check fully self-contained and auditable: this
                // subquery can only ever match a reference that (a) belongs
                // to the exact rendition already pinned by UUID above via
                // the `HasMany` foreign key, and (b) independently declares
                // the same Church, and (c) is not deactivated.
                $query->withoutGlobalScope('church_tenant')
                    ->where('church_id', $churchId)
                    ->whereNull('deactivated_at');
            })
            ->first();

        if ($rendition === null || ! Storage::disk($rendition->disk)->exists($rendition->path)) {
            return null;
        }

        return [
            'url' => config('public-website.asset_origin').'/media/'.$rendition->uuid,
            'alt' => $alt ?? MediaAsset::withoutGlobalScopes()->withTrashed()->find($rendition->media_asset_id)?->alt_text ?? '',
            'width' => $rendition->width,
            'height' => $rendition->height,
        ];
    }

    /** @return array{url: string, alt: string, width: int|null, height: int|null}|null */
    public function image(int $churchId, ?int $assetId, ?string $altOverride = null): ?array
    {
        if ($assetId === null) {
            return null;
        }

        $asset = MediaAsset::withoutGlobalScope('church_tenant')
            ->where('church_id', $churchId)
            ->find($assetId);

        $allowedMedia = [
            'image/jpeg' => ['jpg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];

        if ($asset === null || $asset->disk !== 'public' || ! isset($allowedMedia[$asset->mime_type])) {
            return null;
        }

        $expectedPrefix = "tenants/{$churchId}/media/{$asset->uuid}/";

        $filename = substr($asset->path, strlen($expectedPrefix));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! str_starts_with($asset->path, $expectedPrefix)
            || str_contains(substr($asset->path, strlen($expectedPrefix)), '/')
            || pathinfo($filename, PATHINFO_FILENAME) !== 'original'
            || ! in_array($extension, $allowedMedia[$asset->mime_type], true)
            || ! Storage::disk('public')->exists($asset->path)) {
            return null;
        }

        return [
            'url' => Storage::disk('public')->url($asset->path),
            'alt' => $altOverride ?? $asset->alt_text ?? '',
            'width' => $asset->width,
            'height' => $asset->height,
        ];
    }
}

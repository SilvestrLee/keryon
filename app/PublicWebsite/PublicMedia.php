<?php

namespace App\PublicWebsite;

use App\Enums\MediaRenditionState;
use App\Models\MediaAsset;
use App\Models\MediaRendition;
use Illuminate\Support\Facades\Storage;

class PublicMedia
{
    /** @return array{url: string, alt: string, width: int|null, height: int|null}|null */
    public function rendition(?string $uuid, ?string $alt = null): ?array
    {
        if ($uuid === null) {
            return null;
        }

        $rendition = MediaRendition::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->where('state', MediaRenditionState::Active->value)
            ->whereHas('publicReferences', fn ($query) => $query->whereNull('deactivated_at'))
            ->first();

        if ($rendition === null || ! Storage::disk($rendition->disk)->exists($rendition->path)) {
            return null;
        }

        return [
            'url' => route('media.public', ['rendition' => $rendition->uuid]),
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

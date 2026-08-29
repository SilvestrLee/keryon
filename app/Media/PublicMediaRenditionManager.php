<?php

namespace App\Media;

use App\Enums\AssetUse;
use App\Enums\MediaPublicConsumer;
use App\Enums\MediaRenditionState;
use App\Enums\MediaRenditionVariant;
use App\Models\MediaAsset;
use App\Models\MediaPublicReference;
use App\Models\MediaRendition;
use App\Trust\Rights\AssetRightsPolicy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicMediaRenditionManager
{
    public function __construct(private readonly AssetRightsPolicy $rights) {}

    public function rendition(MediaAsset $asset, int $churchId): MediaRendition
    {
        if ($asset->trashed() || $asset->church_id !== $churchId) {
            throw ValidationException::withMessages(['media' => 'The selected media is not available for this Church.']);
        }

        $this->rights->ensure($asset, AssetUse::Publish);

        $source = Storage::disk($asset->disk)->get($asset->path);
        $dimensions = is_string($source) ? @getimagesizefromstring($source) : false;

        if (! is_string($source) || $dimensions === false || ! in_array($asset->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['media' => 'The selected media cannot be published safely.']);
        }

        $sourceSha = hash('sha256', $source);

        if ($asset->sha256 !== null && ! hash_equals($asset->sha256, $sourceSha)) {
            throw ValidationException::withMessages(['media' => 'The selected media failed its integrity check.']);
        }

        if ($asset->sha256 === null) {
            $asset->forceFill(['sha256' => $sourceSha])->save();
        }

        $existing = MediaRendition::withoutGlobalScopes()
            ->where('media_asset_id', $asset->id)
            ->where('source_sha256', $sourceSha)
            ->where('variant', MediaRenditionVariant::WebOriginal->value)
            ->first();

        if ($existing?->state === MediaRenditionState::Active && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }

        $uuid = (string) Str::uuid();
        $extension = match ($asset->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        $disk = (string) config('media.public_disk', 'media-public');
        $path = "renditions/{$uuid}/web-original.{$extension}";

        if (! Storage::disk($disk)->put($path, $source)) {
            throw ValidationException::withMessages(['media' => 'The public media rendition could not be stored.']);
        }

        try {
            $values = [
                'uuid' => $uuid,
                'church_id' => $churchId,
                'media_asset_id' => $asset->id,
                'variant' => MediaRenditionVariant::WebOriginal,
                'state' => MediaRenditionState::Active,
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $asset->mime_type,
                'size' => strlen($source),
                'width' => $dimensions[0],
                'height' => $dimensions[1],
                'sha256' => hash('sha256', $source),
                'source_sha256' => $sourceSha,
                'activated_at' => now(),
                'revoked_at' => null,
            ];

            if ($existing !== null) {
                $existing->forceFill($values)->save();

                return $existing;
            }

            return MediaRendition::withoutGlobalScopes()->create($values);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function reference(MediaRendition $rendition, int $churchId, int $publicationId, string $usageKey): MediaPublicReference
    {
        if ($rendition->church_id !== $churchId || $rendition->state !== MediaRenditionState::Active) {
            throw ValidationException::withMessages(['media' => 'A public media reference must remain within its Church.']);
        }

        return MediaPublicReference::withoutGlobalScopes()->updateOrCreate(
            [
                'consumer_type' => MediaPublicConsumer::WebsitePublication->value,
                'consumer_id' => $publicationId,
                'usage_key' => $usageKey,
            ],
            [
                'church_id' => $churchId,
                'media_rendition_id' => $rendition->id,
                'activated_at' => now(),
                'deactivated_at' => null,
            ],
        );
    }
}

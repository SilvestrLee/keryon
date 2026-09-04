<?php

namespace App\Communications\OrganizationInbox\Import;

use App\Models\MediaAsset;
use App\Models\OrganizationCommunicationAsset;
use App\Trust\Rights\RecordMediaAssetRights;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * K-ORG-COMMS-001E §18-§21 — copies an eligible Organization
 * Communication asset into a brand-new, Church-owned `MediaAsset`,
 * through the same canonical storage boundary `MediaSelectField::ingest()`
 * uses for a normal upload (`tenants/{church_id}/media/{uuid}/original.
 * {ext}` on the shared private disk). The Organization's own asset row
 * and bytes are only ever *read* — never moved, deleted, or re-owned
 * (§18). Authorization is deliberately not performed here — the caller's
 * (`OrganizationCommunicationImportService`'s) responsibility, which has
 * already checked `Capability::MediaManage` via the import plan.
 *
 * §71 — if anything fails after the new blob is written, the blob is
 * deleted before the exception propagates. No orphan storage.
 */
class ImportOrganizationCommunicationAsset
{
    public function handle(OrganizationCommunicationAsset $source, int $churchId, bool $attributionRequired, ?string $attributionText): MediaAsset
    {
        Gate::authorize('create', MediaAsset::class);

        $disk = Storage::disk($source->disk);
        $uuid = (string) Str::uuid();
        $extension = $this->extensionFor($source->mime_type);
        $finalPath = "tenants/{$churchId}/media/{$uuid}/original.{$extension}";

        $bytes = $disk->get($source->path);

        if ($bytes === null) {
            throw new RuntimeException('The Organization asset could not be read for import.');
        }

        if (! $disk->put($finalPath, $bytes)) {
            throw new RuntimeException('The image could not be stored. Please try again.');
        }

        try {
            $asset = new MediaAsset([
                'disk' => $source->disk,
                'path' => $finalPath,
                'original_filename' => $source->original_filename,
                'mime_type' => $source->mime_type,
                'size' => $source->size,
                'sha256' => hash('sha256', $bytes),
                'width' => $source->width,
                'height' => $source->height,
                'alt_text' => $source->alt_text,
            ]);
            $asset->uuid = $uuid;
            $asset->save();

            app(RecordMediaAssetRights::class)->organizationShared($asset, $attributionRequired, $attributionText);
        } catch (Throwable $exception) {
            $disk->delete($finalPath);

            throw $exception;
        }

        return $asset;
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException("Unsupported Church Media MIME type [{$mimeType}]."),
        };
    }
}

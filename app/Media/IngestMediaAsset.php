<?php

namespace App\Media;

use App\Filament\Support\MediaSelectField;
use App\Models\MediaAsset;
use App\Support\TenantContext;
use App\Trust\Rights\RecordMediaAssetRights;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * K-MEDIA-V1-001B §13/§14 — the single canonical Church Media creation
 * boundary, extracted unchanged from `MediaSelectField::ingest()`
 * (K-CHURCHWEB-001C-R1) so the Media Library's own direct-upload action
 * and the pre-existing form-field picker call exactly one ingestion path
 * rather than two. `MediaSelectField::ingest()` now delegates here — its
 * public signature, validation behaviour, and every existing regression
 * test (`MediaIngestionTest`) are unchanged.
 *
 * Every invariant from the original implementation is preserved
 * verbatim: JPEG/PNG/WebP only, 10 MB cap (both constants remain owned
 * by `MediaSelectField` as the historical public contract — this class
 * reads them rather than redefining them, so there is exactly one source
 * of truth), server-side MIME re-detection (never the client-supplied
 * MIME), real `getimagesize()` decode verification, per-Church staging
 * path validation, a normalized canonical `tenants/{church_id}/media/
 * {uuid}/original.{ext}` storage path, SHA-256 computation, and
 * immediate rights evidence via `RecordMediaAssetRights::churchDeclared()`.
 */
class IngestMediaAsset
{
    public function handle(string $stagingPath, string $originalFilename, ?string $altText = null): MediaAsset
    {
        Gate::authorize('create', MediaAsset::class);

        $diskName = (string) config('media.private_disk', 'media-private');
        $disk = Storage::disk($diskName);
        $churchId = app(TenantContext::class)->currentChurchId();

        if ($churchId === null) {
            throw ValidationException::withMessages([
                'upload' => 'A Church must be selected before uploading media.',
            ]);
        }

        $stagingPrefix = "tenants/{$churchId}/media/.staging/";
        $stagingFilename = substr($stagingPath, strlen($stagingPrefix));

        if (! str_starts_with($stagingPath, $stagingPrefix)
            || $stagingFilename === ''
            || str_contains($stagingFilename, '/')
            || ! $disk->exists($stagingPath)) {
            throw ValidationException::withMessages([
                'upload' => 'The uploaded image is invalid for the current Church.',
            ]);
        }

        $mimeType = $disk->mimeType($stagingPath) ?: 'application/octet-stream';
        $size = $disk->size($stagingPath);
        $dimensions = @getimagesize($disk->path($stagingPath));

        if (! in_array($mimeType, MediaSelectField::ACCEPTED_MIME_TYPES, true) || $dimensions === false) {
            throw ValidationException::withMessages([
                'upload' => 'The image must be a JPEG, PNG, or WebP file.',
            ]);
        }

        if ($size > MediaSelectField::MAX_UPLOAD_SIZE_KB * 1024) {
            throw ValidationException::withMessages([
                'upload' => 'The image must not be larger than 10 MB.',
            ]);
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        $uuid = (string) Str::uuid();

        // K-CHURCHWEB-001C-R1 §4-§5 — the canonical tenant-owned asset
        // hierarchy: one UUID-identified directory per asset. The
        // physical filename inside it is normalized, not the human
        // upload name, so uniqueness never depends on what the user
        // happened to call their file.
        $finalPath = "tenants/{$churchId}/media/{$uuid}/original.{$extension}";

        if (! $disk->move($stagingPath, $finalPath)) {
            throw ValidationException::withMessages([
                'upload' => 'The image could not be stored. Please try again.',
            ]);
        }

        [$width, $height] = $dimensions;

        $asset = new MediaAsset([
            'disk' => $diskName,
            'path' => $finalPath,
            'original_filename' => basename($originalFilename),
            'mime_type' => $mimeType,
            'size' => $size,
            'sha256' => hash('sha256', $disk->get($finalPath)),
            'width' => $width ?: null,
            'height' => $height ?: null,
            'alt_text' => $altText,
        ]);
        // Set explicitly (not fillable) so the DB row's identity matches
        // the directory we just created it under exactly — MediaAsset's
        // own uuid-generation only fires when the column is still empty,
        // so this is respected, not overridden.
        $asset->uuid = $uuid;
        try {
            $asset->save();
            app(RecordMediaAssetRights::class)->churchDeclared($asset);
        } catch (\Throwable $exception) {
            $disk->delete($finalPath);

            throw $exception;
        }

        return $asset;
    }
}

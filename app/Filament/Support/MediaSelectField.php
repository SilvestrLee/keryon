<?php

namespace App\Filament\Support;

use App\Models\MediaAsset;
use App\Support\TenantContext;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * K-CHURCHWEB-001C §25/§26, corrected by K-CHURCHWEB-001C-R1 — the
 * smallest safe media interaction this milestone authorizes: pick an
 * existing institutional MediaAsset, or upload a new one inline.
 * Deliberately NOT a full Media Library (no collections, no folders, no
 * bulk operations, no image editor — see K-CHURCHWEB-001B §2.6).
 * `->options()` uses a plain closure over `MediaAsset::query()` rather
 * than Filament's `->relationship()` magic, since these singleton pages
 * don't bind an Eloquent model to the form schema — `MediaAsset`'s own
 * `BelongsToChurch` global scope already keeps the option list
 * tenant-safe with zero extra scoping here.
 *
 * K-CHURCHWEB-001C-R1 §3-§5 — storage contract correction. The upload
 * first lands in a per-Church staging area (still tenant-scoped, never a
 * cross-tenant shared path), then `ingest()` — called once, at the
 * moment the asset is actually confirmed — generates the asset's real
 * UUID identity and *moves* the file into its canonical
 * `tenants/{church_id}/media/{uuid}/{filename}` home before the
 * MediaAsset row is created, so the DB row's own `uuid` column and the
 * physical folder name always match exactly. The physical filename
 * inside that folder is normalized (`original.{ext}`), not the human
 * upload name — uniqueness comes from the UUID directory, not from
 * trusting a user-supplied filename, so two churches (or two uploads by
 * the same church) can never collide or overwrite each other even if
 * both are literally named "logo.png". `original_filename` still
 * captures the real human filename as metadata — the R1 correction only
 * changes what's physically *stored*, not what's *remembered*. `ingest()`
 * is a separate, directly-testable method rather than an inline closure
 * specifically so regression tests don't have to drive Filament's
 * `createOptionForm` action machinery to prove the storage contract.
 */
class MediaSelectField
{
    /**
     * K-CHURCHWEB-001C-R1 §6 — the v1 institutional-media ingestion
     * contract: standard web raster formats only. SVG is deliberately
     * excluded — Keryon has no SVG sanitization policy today, and an
     * unsanitized SVG upload is a genuine XSS vector; see the R1 report
     * §9 for the full rationale. Revisit only alongside an explicit
     * sanitization decision, not by silently adding it here.
     */
    public const ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * K-CHURCHWEB-001C-R1 §7 — 10 MB. Generous enough for high-resolution
     * web photography (a church's own hero/leadership photos) without
     * leaving ingestion effectively unbounded. Kilobytes, per Filament's
     * `maxSize()` convention.
     */
    public const MAX_UPLOAD_SIZE_KB = 10240;

    public static function make(string $column, string $label): Select
    {
        return Select::make($column)
            ->label($label)
            ->options(fn () => MediaAsset::query()->pluck('original_filename', 'id'))
            ->searchable()
            ->preload()
            ->native(false)
            ->createOptionForm([
                FileUpload::make('upload')
                    ->label('Image')
                    ->image()
                    ->acceptedFileTypes(self::ACCEPTED_MIME_TYPES)
                    ->maxSize(self::MAX_UPLOAD_SIZE_KB)
                    ->disk('public')
                    // Tenant-scoped staging only — never a cross-tenant or
                    // Website-specific path. Moved to its canonical
                    // per-asset home in ingest() below before the
                    // MediaAsset row is created; nothing is ever read
                    // back from this staging path afterward.
                    ->directory(fn () => 'tenants/'.app(TenantContext::class)->currentChurchId().'/media/.staging')
                    // Keep Filament's generated storage name so identical
                    // user filenames cannot collide in staging. The real
                    // client filename is carried separately as metadata.
                    ->storeFileNamesIn('original_filename')
                    ->required()
                    ->helperText('JPEG, PNG, or WebP — up to 10 MB.'),
                TextInput::make('alt_text')
                    ->label('Alt text')
                    ->helperText('Describes the image for screen readers — used whenever this image is shown without a more specific caption.')
                    ->maxLength(255),
            ])
            ->createOptionUsing(fn (array $data): int => self::ingest(
                $data['upload'],
                $data['original_filename'],
                $data['alt_text'] ?? null,
            )->id);
    }

    /**
     * Moves an already-uploaded file from its (tenant-scoped) staging
     * path into its canonical per-asset home and creates the owning
     * MediaAsset row. Public and static specifically so regression tests
     * can exercise the real storage contract directly, without driving
     * Filament's `createOptionForm` action machinery.
     */
    public static function ingest(string $stagingPath, string $originalFilename, ?string $altText = null): MediaAsset
    {
        $disk = Storage::disk('public');
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

        if (! in_array($mimeType, self::ACCEPTED_MIME_TYPES, true) || $dimensions === false) {
            throw ValidationException::withMessages([
                'upload' => 'The image must be a JPEG, PNG, or WebP file.',
            ]);
        }

        if ($size > self::MAX_UPLOAD_SIZE_KB * 1024) {
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
            'disk' => 'public',
            'path' => $finalPath,
            'original_filename' => basename($originalFilename),
            'mime_type' => $mimeType,
            'size' => $size,
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
        } catch (\Throwable $exception) {
            $disk->delete($finalPath);

            throw $exception;
        }

        return $asset;
    }
}

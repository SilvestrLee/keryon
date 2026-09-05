<?php

namespace App\Filament\Support;

use App\Media\IngestMediaAsset;
use App\Models\MediaAsset;
use App\Support\TenantContext;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

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
                    ->disk(config('media.private_disk', 'media-private'))
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
     * K-MEDIA-V1-001B §13/§14 — delegates to `IngestMediaAsset`, the
     * extracted canonical ingestion service, so this form field and the
     * Media Library's own direct-upload action share exactly one
     * implementation. Public and static specifically so regression tests
     * can exercise the real storage contract directly, without driving
     * Filament's `createOptionForm` action machinery. Signature and
     * behaviour are unchanged from before the extraction.
     */
    public static function ingest(string $stagingPath, string $originalFilename, ?string $altText = null): MediaAsset
    {
        return app(IngestMediaAsset::class)->handle($stagingPath, $originalFilename, $altText);
    }
}

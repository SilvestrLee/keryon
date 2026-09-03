<?php

namespace App\Organizations\Communications;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationAssetRightsBasis;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationRevision;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * K-ORG-COMMS-001B §34-§48 — Organization-owned communication asset
 * lifecycle. Mirrors the staging-then-ingest storage contract already
 * proven by `App\Filament\Support\MediaSelectField` (K-CHURCHWEB-001C-R1),
 * but resolves ownership from `OrganizationContext`, never
 * `TenantContext`, and stores under `organizations/{uuid}/communications/
 * {uuid}/{version}/{asset uuid}/...` — never `tenants/{church_id}/...`.
 */
class OrganizationCommunicationAssetManager
{
    /** @var list<string> */
    public const ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public const MAX_UPLOAD_SIZE_KB = 15360;

    public function __construct(
        private readonly OrganizationCommunicationAuthorizer $authorizer,
        private readonly OrganizationCommunicationAudit $audit,
        private readonly OrganizationContext $context,
    ) {}

    public function stagingDirectory(): string
    {
        $organization = $this->context->currentOrganization()
            ?? throw new LogicException('An active Organization workspace is required.');

        return "organizations/{$organization->uuid}/communications/.staging";
    }

    /** @param array{rights_basis: OrganizationCommunicationAssetRightsBasis|string, usage_guidance?: ?string, attribution_required?: bool, attribution_text?: ?string, alt_text?: ?string} $attributes */
    public function addAsset(
        OrganizationCommunicationRevision $revision,
        string $stagingPath,
        string $originalFilename,
        array $attributes,
    ): OrganizationCommunicationAsset {
        return DB::transaction(function () use ($revision, $stagingPath, $originalFilename, $attributes): OrganizationCommunicationAsset {
            $locked = OrganizationCommunicationRevision::query()
                ->with('communication')
                ->lockForUpdate()
                ->findOrFail($revision->id);
            $actor = $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $locked->communication);
            $this->assertDraft($locked);

            $organization = $actor->organization;
            $diskName = (string) config('media.private_disk', 'media-private');
            $disk = Storage::disk($diskName);
            $stagingPrefix = "organizations/{$organization->uuid}/communications/.staging/";

            if (! str_starts_with($stagingPath, $stagingPrefix)
                || str_contains(substr($stagingPath, strlen($stagingPrefix)), '/')
                || ! $disk->exists($stagingPath)) {
                throw ValidationException::withMessages(['upload' => 'The uploaded file is invalid for this Organization.']);
            }

            $mimeType = $disk->mimeType($stagingPath) ?: 'application/octet-stream';
            $size = $disk->size($stagingPath);

            if (! in_array($mimeType, self::ACCEPTED_MIME_TYPES, true)) {
                throw ValidationException::withMessages(['upload' => 'Files must be JPEG, PNG, WebP, or PDF.']);
            }

            if ($size > self::MAX_UPLOAD_SIZE_KB * 1024) {
                throw ValidationException::withMessages(['upload' => 'Files must not be larger than 15 MB.']);
            }

            $dimensions = str_starts_with($mimeType, 'image/') ? @getimagesize($disk->path($stagingPath)) : false;
            if (str_starts_with($mimeType, 'image/') && $dimensions === false) {
                throw ValidationException::withMessages(['upload' => 'The image could not be read.']);
            }

            $rightsBasis = $attributes['rights_basis'] instanceof OrganizationCommunicationAssetRightsBasis
                ? $attributes['rights_basis']
                : OrganizationCommunicationAssetRightsBasis::tryFrom((string) $attributes['rights_basis']);
            if ($rightsBasis === null) {
                throw ValidationException::withMessages(['rights_basis' => 'Choose a supported rights basis.']);
            }

            $attributionRequired = (bool) ($attributes['attribution_required'] ?? false);
            $attributionText = filled($attributes['attribution_text'] ?? null) ? trim((string) $attributes['attribution_text']) : null;
            if ($attributionRequired && $attributionText === null) {
                throw ValidationException::withMessages(['attribution_text' => 'Attribution text is required when attribution is marked as required.']);
            }

            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
            };
            $assetUuid = (string) Str::uuid();
            $finalPath = "organizations/{$organization->uuid}/communications/{$locked->communication->uuid}/{$locked->version}/{$assetUuid}/original.{$extension}";

            if (! $disk->move($stagingPath, $finalPath)) {
                throw ValidationException::withMessages(['upload' => 'The file could not be stored. Please try again.']);
            }

            $sha256 = hash('sha256', $disk->get($finalPath));
            [$width, $height] = $dimensions ?: [null, null];

            try {
                $asset = new OrganizationCommunicationAsset;
                $asset->uuid = $assetUuid;
                $asset->forceFill([
                    'organization_id' => $organization->id,
                    'organization_communication_id' => $locked->communication->id,
                    'organization_communication_revision_id' => $locked->id,
                    'uploaded_by_organization_membership_id' => $actor->id,
                    'disk' => $diskName,
                    'path' => $finalPath,
                    'original_filename' => basename($originalFilename),
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'sha256' => $sha256,
                    'width' => $width ?: null,
                    'height' => $height ?: null,
                    'alt_text' => filled($attributes['alt_text'] ?? null) ? trim((string) $attributes['alt_text']) : null,
                    'rights_basis' => $rightsBasis,
                    'usage_guidance' => filled($attributes['usage_guidance'] ?? null) ? trim((string) $attributes['usage_guidance']) : null,
                    'attribution_required' => $attributionRequired,
                    'attribution_text' => $attributionText,
                ])->save();
            } catch (\Throwable $exception) {
                $disk->delete($finalPath);

                throw $exception;
            }

            $this->audit->record(
                OrganizationAuditEventType::COMMUNICATION_ASSET_ADDED,
                $asset,
                $actor,
                ['mime_type' => $mimeType, 'size' => $size],
            );

            return $asset->fresh();
        }, 3);
    }

    public function removeAsset(OrganizationCommunicationAsset $asset): void
    {
        DB::transaction(function () use ($asset): void {
            $locked = OrganizationCommunicationAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $revision = OrganizationCommunicationRevision::query()
                ->with('communication')
                ->lockForUpdate()
                ->findOrFail($locked->organization_communication_revision_id);
            $actor = $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $revision->communication);
            $this->assertDraft($revision);

            $disk = Storage::disk($locked->disk);
            $path = $locked->path;
            $locked->delete();

            $stillReferenced = OrganizationCommunicationAsset::query()
                ->where('disk', $locked->disk)
                ->where('path', $path)
                ->exists();
            if (! $stillReferenced) {
                $disk->delete($path);
            }

            $this->audit->record(
                OrganizationAuditEventType::COMMUNICATION_ASSET_REMOVED,
                $revision->communication,
                $actor,
                ['revision_version' => $revision->version],
            );
        }, 3);
    }

    private function assertDraft(OrganizationCommunicationRevision $revision): void
    {
        if ($revision->state !== OrganizationCommunicationRevisionState::DRAFT) {
            throw new LogicException('Organization communication assets may only change on a Draft revision.');
        }
    }
}

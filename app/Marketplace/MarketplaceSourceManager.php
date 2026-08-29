<?php

namespace App\Marketplace;

use App\Enums\MarketplaceSourceAvailability;
use App\Enums\MarketplaceSourceValidationStatus;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceSourceVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceSourceManager
{
    public function __construct(
        private readonly MarketplaceSourceIntegrity $integrity,
        private readonly MarketplaceRightsGate $rights,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function register(MarketplaceItem $item, int $version, string $bytes, string $originalFilename, array $metadata = []): MarketplaceSourceVersion
    {
        if ($item->trashed() || $version < 1) {
            throw ValidationException::withMessages(['source' => 'A Marketplace source version requires an active item and a positive version number.']);
        }

        $identity = $this->integrity->inspect($bytes, $originalFilename);
        $disk = config('marketplace.source_disk', 'marketplace');
        $storageKey = 'marketplace/sources/'.Str::uuid().'/source.'.$identity['extension'];

        if (! Storage::disk($disk)->put($storageKey, $bytes)) {
            throw ValidationException::withMessages(['source' => 'The Marketplace source package could not be stored.']);
        }

        try {
            return DB::transaction(function () use ($item, $version, $originalFilename, $metadata, $identity, $disk, $storageKey): MarketplaceSourceVersion {
                $source = new MarketplaceSourceVersion([
                    'version' => $version,
                    'original_filename' => basename($originalFilename),
                    'compatibility_metadata' => $metadata['compatibility_metadata'] ?? null,
                    'creator_name' => $metadata['creator_name'] ?? null,
                    'rightsholder_name' => $metadata['rightsholder_name'] ?? null,
                    'license_reference' => $metadata['license_reference'] ?? null,
                    'licensing_metadata' => $metadata['licensing_metadata'] ?? null,
                    'font_metadata' => $metadata['font_metadata'] ?? null,
                ]);
                $source->forceFill([
                    'marketplace_item_id' => $item->id,
                    'disk' => $disk,
                    'storage_key' => $storageKey,
                    'mime_type' => $identity['mimeType'],
                    'extension' => $identity['extension'],
                    'size' => $identity['size'],
                    'sha256' => $identity['sha256'],
                    'validation_status' => MarketplaceSourceValidationStatus::VALID,
                    'availability_status' => MarketplaceSourceAvailability::DRAFT,
                ])->save();

                return $source->fresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storageKey);
            throw $exception;
        }
    }

    public function makeAvailable(MarketplaceSourceVersion $source): MarketplaceSourceVersion
    {
        if ($source->validation_status !== MarketplaceSourceValidationStatus::VALID) {
            throw ValidationException::withMessages(['source' => 'Only a valid Marketplace source package can become available.']);
        }

        if (! $this->rights->permits($source)) {
            throw ValidationException::withMessages(['source' => 'Marketplace source rights must be verified before the package can become available.']);
        }

        $bytes = Storage::disk($source->disk)->get($source->getRawOriginal('storage_key'));

        if (! is_string($bytes) || ! hash_equals($source->sha256, hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['source' => 'Marketplace source package integrity verification failed.']);
        }

        DB::table('marketplace_source_versions')->where('id', $source->id)->update([
            'availability_status' => MarketplaceSourceAvailability::AVAILABLE->value,
            'available_at' => now(),
            'updated_at' => now(),
        ]);

        return $source->fresh();
    }

    public function withdraw(MarketplaceSourceVersion $source): MarketplaceSourceVersion
    {
        DB::table('marketplace_source_versions')->where('id', $source->id)->update([
            'availability_status' => MarketplaceSourceAvailability::WITHDRAWN->value,
            'updated_at' => now(),
        ]);

        return $source->fresh();
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\MediaRenditionState;
use App\Models\MediaAsset;
use App\Models\MediaPublicReference;
use App\Models\MediaRendition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventoryMedia extends Command
{
    protected $signature = 'media:inventory';

    protected $description = 'Report non-destructive Media storage and reference counts';

    public function handle(): int
    {
        $assets = MediaAsset::withoutGlobalScopes()->withTrashed()->get();
        $missing = $assets->filter(fn (MediaAsset $asset): bool => ! Storage::disk($asset->disk)->exists($asset->path))->count();
        $knownPublicPaths = MediaRendition::withoutGlobalScopes()->pluck('path')->all();
        $publicFiles = Storage::disk(config('media.public_disk', 'media-public'))->allFiles();

        $inventory = [
            'media_assets_total' => $assets->count(),
            'legacy_public_originals' => $assets->where('disk', 'public')->count(),
            'private_originals' => $assets->where('disk', config('media.private_disk', 'media-private'))->count(),
            'missing_original_bytes' => $missing,
            'missing_sha256' => $assets->whereNull('sha256')->count(),
            'website_legacy_media_references' => $this->websiteReferences(),
            'campaign_media_references' => DB::table('campaign_media')->count(),
            'design_media_references' => DB::table('design_media')->count(),
            'design_outputs_with_media' => DB::table('design_outputs')->whereNotNull('media_asset_id')->count(),
            'renditions_active' => MediaRendition::withoutGlobalScopes()->where('state', MediaRenditionState::Active->value)->count(),
            'renditions_unreferenced' => MediaRendition::withoutGlobalScopes()
                ->whereNotIn('id', MediaPublicReference::withoutGlobalScopes()->whereNull('deactivated_at')->select('media_rendition_id'))
                ->count(),
            'public_references_active' => MediaPublicReference::withoutGlobalScopes()->whereNull('deactivated_at')->count(),
            'orphan_public_objects' => count(array_diff($publicFiles, $knownPublicPaths)),
        ];

        $this->table(['Metric', 'Count'], collect($inventory)->map(fn ($count, $name) => [$name, $count])->all());

        return self::SUCCESS;
    }

    private function websiteReferences(): int
    {
        return DB::table('website_home_contents')->whereNotNull('hero_image_id')->count()
            + DB::table('website_leadership_profiles')->whereNotNull('photo_id')->count()
            + DB::table('website_ministries')->whereNotNull('image_id')->count()
            + DB::table('church_brand_profiles')->whereNotNull('primary_logo_media_id')->count()
            + DB::table('church_brand_profiles')->whereNotNull('mark_media_id')->count();
    }
}

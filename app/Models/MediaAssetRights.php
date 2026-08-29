<?php

namespace App\Models;

use App\Enums\AssetProvenance;
use App\Enums\AssetRightsStatus;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MediaAssetRights extends Model
{
    use BelongsToChurch;

    protected $table = 'media_asset_rights';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provenance' => AssetProvenance::class,
            'status' => AssetRightsStatus::class,
            'allowed_uses' => 'array',
            'attribution_required' => 'boolean',
            'declared_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rights): void {
            $asset = MediaAsset::withoutGlobalScopes()->withTrashed()->find($rights->media_asset_id);

            if ($asset === null || $asset->church_id !== $rights->church_id) {
                throw new LogicException('Media rights must belong to the same Church as their MediaAsset.');
            }
        });
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->withTrashed();
    }

    public function declarer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by');
    }
}

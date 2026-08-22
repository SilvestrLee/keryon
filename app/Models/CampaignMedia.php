<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CampaignMedia extends Model
{
    use BelongsToChurch;

    protected $table = 'campaign_media';

    protected $fillable = [
        'label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $association): void {
            $campaign = Campaign::query()->find($association->campaign_id);
            $asset = MediaAsset::withTrashed()->find($association->media_asset_id);

            if (
                $campaign === null
                || $asset === null
                || $campaign->church_id !== $association->church_id
                || $asset->church_id !== $association->church_id
            ) {
                throw new LogicException('Campaign media must belong to the same active Church.');
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->withTrashed();
    }
}

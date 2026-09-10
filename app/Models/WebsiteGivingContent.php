<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * K-WEB-V1-001D-C §42-46 — one row per Church, following the exact
 * `WebsiteHomeContent`/`WebsiteAboutContent` singleton pattern. No
 * transactional field exists — see the migration's own docblock.
 */
class WebsiteGivingContent extends Model
{
    use BelongsToChurch;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'headline',
        'body',
        'image_id',
        'image_alt_override',
        'cta_label',
        'giving_url',
        'additional_instructions',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_id');
    }

    public function imageAltText(): ?string
    {
        return $this->image_alt_override ?? $this->image?->alt_text;
    }

    public function mediaAssetForeignKeys(): array
    {
        return ['image_id'];
    }
}

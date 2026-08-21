<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * K-CHURCHWEB-001B §19 ("Home" section). One row per Church. Semantic
 * structured columns only (§20) — Hero, Welcome, Scripture Highlight.
 * "Featured Ministries"/"Featured Campaign"/"Latest News" are
 * deliberately deferred — see the report §11 for why.
 */
class WebsiteHomeContent extends Model
{
    use BelongsToChurch;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'hero_heading',
        'hero_subheading',
        'hero_cta_label',
        'hero_cta_url',
        'hero_image_id',
        'hero_image_alt_override',
        'welcome_heading',
        'welcome_body',
        'scripture_reference',
        'scripture_text',
    ];

    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hero_image_id');
    }

    /**
     * K-CHURCHWEB-001B §15 — usage-level override wins over the asset's
     * own default alt text; falls back to the asset default when no
     * override is set.
     */
    public function heroImageAltText(): ?string
    {
        return $this->hero_image_alt_override ?? $this->heroImage?->alt_text;
    }

    public function mediaAssetForeignKeys(): array
    {
        return ['hero_image_id'];
    }
}

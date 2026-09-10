<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * K-WEB-V1-001D-C §17-24 — informational Website publishing only. See
 * the migration's own docblock for the explicit event-management-ERP
 * exclusion list.
 */
class WebsiteEvent extends Model
{
    use BelongsToChurch;
    use SoftDeletes;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'title',
        'summary',
        'starts_at',
        'ends_at',
        'venue',
        'image_id',
        'image_alt_override',
        'cta_label',
        'cta_url',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

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

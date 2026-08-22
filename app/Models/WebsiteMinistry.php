<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * K-CHURCHWEB-001B §19 ("Ministries" section) + §21 (repeatable
 * structure, modeled relationally — not JSON).
 */
class WebsiteMinistry extends Model
{
    use BelongsToChurch;
    use SoftDeletes;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'name',
        'description',
        'image_id',
        'image_alt_override',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
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

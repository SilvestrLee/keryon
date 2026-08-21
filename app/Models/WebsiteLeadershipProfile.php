<?php

namespace App\Models;

use App\Enums\LeadershipCategory;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * K-CHURCHWEB-001B §19 ("Leadership" section) + §21 (repeatable
 * structure, modeled relationally — not JSON — for ordering, validation,
 * and tenant safety).
 */
class WebsiteLeadershipProfile extends Model
{
    use BelongsToChurch;
    use SoftDeletes;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'name',
        'category',
        'role_title',
        'bio',
        'photo_id',
        'photo_alt_override',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => LeadershipCategory::class,
            'sort_order' => 'integer',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_id');
    }

    public function photoAltText(): ?string
    {
        return $this->photo_alt_override ?? $this->photo?->alt_text;
    }

    public function mediaAssetForeignKeys(): array
    {
        return ['photo_id'];
    }
}

<?php

namespace App\Models;

use App\Enums\PublicationType;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * K-WEB-V1-001D-C §32-41 — the pastor/Church-authored books/resources
 * catalogue. Deliberately named `ChurchPublication`, never `Publication`
 * or `WebsitePublication` (the immutable Website deployment-evidence
 * model) — a Product-Office-locked naming decision (§32). `price_text`
 * is bounded display metadata, never transactional data; Keryon does
 * not process a sale of this content in v1.
 */
class ChurchPublication extends Model
{
    use BelongsToChurch;
    use SoftDeletes;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'title',
        'author',
        'publication_type',
        'description',
        'cover_id',
        'cover_alt_override',
        'price_text',
        'purchase_url',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'publication_type' => PublicationType::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_id');
    }

    public function coverAltText(): ?string
    {
        return $this->cover_alt_override ?? $this->cover?->alt_text;
    }

    public function mediaAssetForeignKeys(): array
    {
        return ['cover_id'];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * K-WEB-V1-001D-C §25-31 — a lightweight public sermon/message
 * catalogue. `media_url` is always an external destination — see the
 * migration's own docblock; Keryon never hosts the referenced video/
 * audio itself.
 */
class WebsiteMessage extends Model
{
    use BelongsToChurch;
    use SoftDeletes;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'title',
        'speaker',
        'message_date',
        'scripture_reference',
        'summary',
        'image_id',
        'image_alt_override',
        'media_url',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'message_date' => 'date',
            'is_featured' => 'boolean',
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

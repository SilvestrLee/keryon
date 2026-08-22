<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * K-CHURCHWEB-001B §12-§16 — an institutional asset, not a Website image.
 * Website, and later Design Studio (as producer) and Campaigns, all
 * consume this same model — see the report §31 seam. The domain
 * primitive only: no collections, no conversions, no transformations, no
 * bulk operations — those are explicitly deferred future-product concerns
 * (§2.6).
 */
class MediaAsset extends Model
{
    use BelongsToChurch;
    use SoftDeletes;

    protected $fillable = [
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $asset) {
            if (empty($asset->uuid)) {
                $asset->uuid = (string) Str::uuid();
            }

            $asset->created_by ??= Auth::id();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaignAssociations(): HasMany
    {
        return $this->hasMany(CampaignMedia::class);
    }
}

<?php

namespace App\Models;

use App\Enums\MediaRenditionState;
use App\Enums\MediaRenditionVariant;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaRendition extends Model
{
    use BelongsToChurch;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variant' => MediaRenditionVariant::class,
            'state' => MediaRenditionState::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'activated_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->withTrashed();
    }

    public function publicReferences(): HasMany
    {
        return $this->hasMany(MediaPublicReference::class);
    }
}

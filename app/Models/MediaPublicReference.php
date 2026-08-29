<?php

namespace App\Models;

use App\Enums\MediaPublicConsumer;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaPublicReference extends Model
{
    use BelongsToChurch;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consumer_type' => MediaPublicConsumer::class,
            'activated_at' => 'immutable_datetime',
            'deactivated_at' => 'immutable_datetime',
        ];
    }

    public function rendition(): BelongsTo
    {
        return $this->belongsTo(MediaRendition::class, 'media_rendition_id');
    }
}

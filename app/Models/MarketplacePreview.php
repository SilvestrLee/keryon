<?php

namespace App\Models;

use App\Enums\MarketplacePreviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MarketplacePreview extends Model
{
    protected $hidden = ['storage_key'];

    protected $fillable = ['type', 'sort_order', 'alt_text'];

    protected function casts(): array
    {
        return [
            'type' => MarketplacePreviewType::class,
            'sort_order' => 'integer',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $preview): void {
            if ($preview->marketplace_source_version_id === null) {
                return;
            }

            $source = MarketplaceSourceVersion::query()->find($preview->marketplace_source_version_id);

            if ($source === null || $source->marketplace_item_id !== $preview->marketplace_item_id) {
                throw new LogicException('A Marketplace preview source version must belong to the same item.');
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketplaceItem::class, 'marketplace_item_id');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSourceVersion::class, 'marketplace_source_version_id');
    }
}

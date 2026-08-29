<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class MarketplaceCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category->slug)) {
                throw new InvalidArgumentException('Marketplace category slugs must use lowercase kebab-case.');
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceItem::class);
    }
}

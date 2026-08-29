<?php

namespace App\Models;

use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplacePublicationStatus;
use App\Enums\MarketplaceRightsStatus;
use App\Enums\MarketplaceSourceAvailability;
use App\Enums\MarketplaceSourceValidationStatus;
use App\Marketplace\MarketplaceRightsGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use LogicException;

class MarketplaceItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_category_id',
        'title',
        'slug',
        'short_description',
        'description',
        'access_type',
        'creator_name',
        'publisher_name',
    ];

    protected function casts(): array
    {
        return [
            'access_type' => MarketplaceAccessType::class,
            'publication_status' => MarketplacePublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $item->slug)) {
                throw new InvalidArgumentException('Marketplace item slugs must use lowercase kebab-case.');
            }

            if ($item->publication_status === MarketplacePublicationStatus::PUBLISHED
                && ($item->currentSourceVersion() === null
                    || ! app(MarketplaceRightsGate::class)->permits($item->currentSourceVersion()))) {
                throw new LogicException('A Marketplace item requires verified source rights before publication.');
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    public function sourceVersions(): HasMany
    {
        return $this->hasMany(MarketplaceSourceVersion::class);
    }

    public function previews(): HasMany
    {
        return $this->hasMany(MarketplacePreview::class);
    }

    public function acquisitions(): HasMany
    {
        return $this->hasMany(MarketplaceAcquisition::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('publication_status', MarketplacePublicationStatus::PUBLISHED->value)
            ->whereNotNull('published_at');
    }

    public function scopeWithDownloadableSource(Builder $query): Builder
    {
        return $query->whereHas('sourceVersions', fn (Builder $source): Builder => $source
            ->where('availability_status', MarketplaceSourceAvailability::AVAILABLE->value)
            ->where('validation_status', MarketplaceSourceValidationStatus::VALID->value)
            ->where('rights_status', MarketplaceRightsStatus::VERIFIED->value));
    }

    public function currentSourceVersion(): ?MarketplaceSourceVersion
    {
        return $this->sourceVersions()
            ->where('availability_status', MarketplaceSourceAvailability::AVAILABLE->value)
            ->where('validation_status', MarketplaceSourceValidationStatus::VALID->value)
            ->orderByDesc('version')
            ->first();
    }

    public function publish(): void
    {
        $source = $this->currentSourceVersion();

        if ($this->trashed() || $source === null || ! app(MarketplaceRightsGate::class)->permits($source)) {
            throw new LogicException('A Marketplace item requires a valid available source version with verified rights before publication.');
        }

        $this->forceFill([
            'publication_status' => MarketplacePublicationStatus::PUBLISHED,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill(['publication_status' => MarketplacePublicationStatus::UNPUBLISHED])->save();
    }
}

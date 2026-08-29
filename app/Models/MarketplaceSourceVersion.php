<?php

namespace App\Models;

use App\Enums\MarketplaceRightsStatus;
use App\Enums\MarketplaceSourceAvailability;
use App\Enums\MarketplaceSourceValidationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MarketplaceSourceVersion extends Model
{
    private const IMMUTABLE_AFTER_AVAILABILITY = [
        'marketplace_item_id',
        'version',
        'disk',
        'storage_key',
        'original_filename',
        'mime_type',
        'extension',
        'size',
        'sha256',
        'validation_status',
        'compatibility_metadata',
    ];

    protected $hidden = ['storage_key'];

    protected $fillable = [
        'version',
        'original_filename',
        'compatibility_metadata',
        'creator_name',
        'rightsholder_name',
        'license_reference',
        'licensing_metadata',
        'font_metadata',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'validation_status' => MarketplaceSourceValidationStatus::class,
            'availability_status' => MarketplaceSourceAvailability::class,
            'rights_status' => MarketplaceRightsStatus::class,
            'compatibility_metadata' => 'array',
            'licensing_metadata' => 'array',
            'font_metadata' => 'array',
            'available_at' => 'datetime',
            'rights_verified_at' => 'datetime',
            'rights_decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $source): void {
            $rightsAttributes = [
                'rights_status', 'creator_name', 'rightsholder_name',
                'license_reference', 'licensing_metadata', 'font_metadata',
                'source_provenance', 'rights_evidence_reference',
                'rights_verified_by_type', 'rights_verified_by_reference',
                'rights_verified_at', 'rights_decided_by_type',
                'rights_decided_by_reference', 'rights_decided_at',
                'rights_decision_reason',
            ];

            if ($source->isDirty($rightsAttributes)) {
                throw new LogicException('Marketplace rights may only change through the trusted rights review action.');
            }

            if ($source->isDirty(['availability_status', 'available_at'])) {
                throw new LogicException('Marketplace source availability may only change through the source manager.');
            }

            foreach (self::IMMUTABLE_AFTER_AVAILABILITY as $attribute) {
                if ($source->isDirty($attribute)) {
                    throw new LogicException('A Marketplace source version cannot have its technical identity changed in place.');
                }
            }

        });

        static::deleting(function (self $source): void {
            if ($source->available_at !== null) {
                throw new LogicException('A Marketplace source version that became available cannot be deleted. Withdraw it instead.');
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketplaceItem::class, 'marketplace_item_id');
    }

    public function previews(): HasMany
    {
        return $this->hasMany(MarketplacePreview::class);
    }

    public function acquisitions(): HasMany
    {
        return $this->hasMany(MarketplaceAcquisition::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(MarketplaceDownload::class);
    }
}

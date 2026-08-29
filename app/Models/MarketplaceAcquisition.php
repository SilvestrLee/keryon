<?php

namespace App\Models;

use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplaceAcquisitionBasis;
use App\Models\Concerns\BelongsToChurch;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MarketplaceAcquisition extends Model
{
    use BelongsToChurch;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'access_type' => MarketplaceAccessType::class,
            'acquisition_basis' => MarketplaceAcquisitionBasis::class,
            'acquired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $acquisition): void {
            $membership = app(TenantContext::class)->currentMembership();

            if ($membership === null
                || $acquisition->church_id !== $membership->church_id
                || ($acquisition->acquired_by !== null && $acquisition->acquired_by !== $membership->user_id)) {
                throw new LogicException('A Marketplace acquisition must use the active Church and trusted actor.');
            }

            $source = MarketplaceSourceVersion::query()->find($acquisition->marketplace_source_version_id);

            if ($source === null || $source->marketplace_item_id !== $acquisition->marketplace_item_id) {
                throw new LogicException('A Marketplace acquisition source version must belong to its item.');
            }
        });

        static::updating(fn (): never => throw new LogicException('Marketplace acquisitions are immutable ownership evidence.'));
        static::deleting(fn (): never => throw new LogicException('Marketplace acquisitions are immutable ownership evidence.'));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketplaceItem::class, 'marketplace_item_id');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSourceVersion::class, 'marketplace_source_version_id');
    }

    public function acquirer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acquired_by');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(MarketplaceDownload::class);
    }
}

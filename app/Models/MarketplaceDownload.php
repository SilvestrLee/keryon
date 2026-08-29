<?php

namespace App\Models;

use App\Enums\MarketplaceDownloadOutcome;
use App\Models\Concerns\BelongsToChurch;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MarketplaceDownload extends Model
{
    use BelongsToChurch;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'outcome' => MarketplaceDownloadOutcome::class,
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $download): void {
            $membership = app(TenantContext::class)->currentMembership();

            if ($membership === null
                || $download->church_id !== $membership->church_id
                || ($download->user_id !== null && $download->user_id !== $membership->user_id)) {
                throw new LogicException('A Marketplace download must use the active Church and trusted actor.');
            }

            $acquisition = MarketplaceAcquisition::withoutGlobalScopes()->find($download->marketplace_acquisition_id);

            if ($acquisition === null
                || $acquisition->church_id !== $download->church_id
                || $acquisition->marketplace_source_version_id !== $download->marketplace_source_version_id) {
                throw new LogicException('A Marketplace download must match its acquisition Church and source version.');
            }

            if ($download->failure_code !== null && ! preg_match('/^[a-z][a-z0-9_]{2,63}$/', $download->failure_code)) {
                throw new LogicException('Marketplace download failure codes must be bounded machine-readable identifiers.');
            }
        });

        static::updating(fn (): never => throw new LogicException('Marketplace download events are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Marketplace download events are append-only.'));
    }

    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAcquisition::class, 'marketplace_acquisition_id');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSourceVersion::class, 'marketplace_source_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

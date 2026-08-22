<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DesignMedia extends Model
{
    use BelongsToChurch;

    protected $table = 'design_media';

    protected $fillable = ['slot_key'];

    protected static function booted(): void
    {
        static::saving(function (self $selection): void {
            $selection->church_id ??= app(TenantContext::class)->currentChurchId();
            $design = Design::withoutGlobalScopes()->find($selection->design_id);
            $asset = MediaAsset::withoutGlobalScopes()->withTrashed()->find($selection->media_asset_id);

            if ($design === null || $asset === null || $design->church_id !== $selection->church_id || $asset->church_id !== $selection->church_id) {
                throw new LogicException('Design media must belong to the same Church as its Design.');
            }
        });
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->withTrashed();
    }
}

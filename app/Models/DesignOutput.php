<?php

namespace App\Models;

use App\Enums\DesignOutputFormat;
use App\Enums\DesignOutputStatus;
use App\Models\Concerns\BelongsToChurch;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class DesignOutput extends Model
{
    use BelongsToChurch;

    protected $fillable = ['format'];

    protected function casts(): array
    {
        return [
            'format' => DesignOutputFormat::class,
            'status' => DesignOutputStatus::class,
            'rendered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $output): void {
            $output->church_id ??= app(TenantContext::class)->currentChurchId();
            $design = Design::withoutGlobalScopes()->find($output->design_id);
            $asset = $output->media_asset_id === null
                ? null
                : MediaAsset::withoutGlobalScopes()->withTrashed()->find($output->media_asset_id);

            if (
                $design === null
                || $design->church_id !== $output->church_id
                || ($output->media_asset_id !== null && ($asset === null || $asset->church_id !== $output->church_id))
            ) {
                throw new LogicException('Design output and generated media must belong to the same Church.');
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

    public function isRendered(): bool
    {
        return $this->status === DesignOutputStatus::RENDERED && $this->media_asset_id !== null;
    }

    public function markRendered(MediaAsset $asset): void
    {
        if ($asset->church_id !== $this->church_id) {
            throw new LogicException('Rendered Design media must belong to the same Church.');
        }

        if ($asset->width !== $this->format->width() || $asset->height !== $this->format->height()) {
            throw new LogicException('Rendered Design media dimensions do not match the requested format.');
        }

        $this->forceFill([
            'media_asset_id' => $asset->id,
            'status' => DesignOutputStatus::RENDERED,
            'failure_code' => null,
            'rendered_at' => now(),
        ])->save();
    }

    public function markFailed(string $code): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{2,63}$/', $code)) {
            throw new InvalidArgumentException('Design rendering failure codes must be bounded machine-readable identifiers.');
        }

        $this->forceFill([
            'media_asset_id' => null,
            'status' => DesignOutputStatus::FAILED,
            'failure_code' => $code,
            'rendered_at' => null,
        ])->save();
    }
}

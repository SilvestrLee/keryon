<?php

namespace App\Media;

use App\Enums\MediaRenditionState;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use LogicException;

class DeleteMediaAsset
{
    public function handle(MediaAsset $asset): void
    {
        Gate::authorize('delete', $asset);

        $hasActivePublicReference = $asset->renditions()
            ->where('state', MediaRenditionState::Active->value)
            ->whereHas('publicReferences', fn ($query) => $query->whereNull('deactivated_at'))
            ->exists();

        if ($hasActivePublicReference) {
            throw new LogicException('Published media must be unpublished before it can be deleted.');
        }

        $asset->delete();
    }

    public function restore(MediaAsset $asset): void
    {
        Gate::authorize('update', $asset);

        $disk = Storage::disk($asset->disk);
        if (! $disk->exists($asset->path)) {
            throw new LogicException('The canonical media bytes are missing.');
        }

        if ($asset->sha256 !== null && ! hash_equals($asset->sha256, hash('sha256', $disk->get($asset->path)))) {
            throw new LogicException('The canonical media failed its integrity check.');
        }

        $asset->restore();
    }
}

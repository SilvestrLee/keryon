<?php

namespace App\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaDelivery
{
    public function response(MediaAsset $asset): StreamedResponse
    {
        Gate::authorize('view', $asset);

        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->response($asset->path, basename($asset->original_filename), [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function url(MediaAsset $asset): string
    {
        Gate::authorize('view', $asset);

        return route('media.private', ['asset' => $asset->uuid]);
    }
}

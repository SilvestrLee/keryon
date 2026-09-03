<?php

namespace App\Organizations\Communications;

use App\Models\OrganizationCommunicationAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * K-ORG-COMMS-001B §38-§39/§48 — mirrors `App\Media\PrivateMediaDelivery`:
 * opaque UUID identity, policy-gated, private/no-store, non-indexable.
 * No public URL is ever generated for an Organization communication
 * asset.
 */
class OrganizationCommunicationAssetDelivery
{
    public function response(OrganizationCommunicationAsset $asset): StreamedResponse
    {
        Gate::authorize('view', $asset);

        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->response($asset->path, basename($asset->original_filename), [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}

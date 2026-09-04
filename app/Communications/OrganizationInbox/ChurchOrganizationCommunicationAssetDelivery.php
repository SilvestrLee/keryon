<?php

namespace App\Communications\OrganizationInbox;

use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationDelivery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * K-ORG-COMMS-001D §21-§24 — the dedicated Church-side asset boundary.
 * Deliberately NOT the Organization-side
 * `OrganizationCommunicationAssetDelivery`/`OrganizationCommunicationAssetController`
 * (§22): that route's policy requires an OrganizationMembership, which a
 * Church staff member never has. This class authorizes through the
 * delivery instead — TenantContext Church, `organization_communications.
 * view`, `delivery.church_id` match, and the asset must belong to the
 * exact revision the delivery references — and preserves the same
 * private/no-store/non-indexable response convention (no public URL is
 * ever generated).
 */
class ChurchOrganizationCommunicationAssetDelivery
{
    public function response(OrganizationCommunicationDelivery $delivery, OrganizationCommunicationAsset $asset): StreamedResponse
    {
        Gate::authorize('viewAsset', [$delivery, $asset]);

        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->response($asset->path, basename($asset->original_filename), [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}

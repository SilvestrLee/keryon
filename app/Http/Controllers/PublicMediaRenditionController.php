<?php

namespace App\Http\Controllers;

use App\Enums\MediaRenditionState;
use App\Models\MediaRendition;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMediaRenditionController extends Controller
{
    /**
     * K-WEB-P0-001 §2/§46 — this route is the actual byte-serving
     * destination every `PublicMedia::rendition()`-produced URL points at,
     * and it carried the identical defect independently: `whereHas`
     * builds a fresh, independently-scoped query against
     * `MediaPublicReference`, whose own `BelongsToChurch` scope fails
     * closed with no `TenantContext` — i.e. for every genuinely anonymous
     * request this route serves. Fixing `PublicMedia::rendition()` alone
     * would still leave every resulting `<img>`/`<link>` URL 404ing for
     * real visitors, so this file is fixed alongside it; see the
     * K-WEB-P0-001 report for the reproduction proving this
     * independently. This route has no Church-scoped route parameter (by
     * design — a public rendition UUID is looked up the same way whether
     * served under a Keryon subdomain or a custom domain), so unlike
     * `PublicMedia::rendition()` there is no separate "requested Church"
     * to cross-check; the safety invariant here is that the outer query
     * already pins to exactly one rendition row (by UUID and Active
     * state) before the relationship subquery ever runs, so removing only
     * that subquery's own tenant scope can never surface a different
     * Church's Media — it can only confirm or deny an active public
     * reference on the one already-identified row.
     */
    public function __invoke(string $rendition): StreamedResponse
    {
        $record = MediaRendition::withoutGlobalScopes()
            ->where('uuid', $rendition)
            ->where('state', MediaRenditionState::Active->value)
            ->whereHas('publicReferences', function ($query): void {
                $query->withoutGlobalScope('church_tenant')->whereNull('deactivated_at');
            })
            ->firstOrFail();

        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);

        return Storage::disk($record->disk)->response($record->path, null, [
            'Content-Type' => $record->mime_type,
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

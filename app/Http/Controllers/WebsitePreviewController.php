<?php

namespace App\Http\Controllers;

use App\Enums\WebsitePageType;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\Themes\ThemeRegistry;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WebsitePreviewController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $tenant,
        PublicWebsiteContent $content,
        ThemeRegistry $themes,
    ): View {
        $page = (string) ($request->route('page') ?? 'home');
        // K-WEB-V1-001D-B §22 — registry-driven, fail-closed: an
        // unrecognized page value never reaches a renderer. Preview does
        // not additionally gate on the page's *enabled* state (§35's own
        // note left this an open, deliberate choice) — a Church may
        // still preview a currently-disabled page while deciding whether
        // to re-enable it; only theme support and a known canonical
        // identity are required.
        $pageType = WebsitePageType::tryFrom($page);
        abort_if($pageType === null, 404);

        $church = $tenant->currentChurch();
        abort_if($church === null, 404);
        $settings = $content->settings($church->getKey());
        $theme = $settings ? $themes->resolve((string) $settings->getRawOriginal('theme')) : null;
        abort_if($theme === null, 404);
        abort_unless(in_array($pageType, $theme->supportedPageTypes(), true), 404);

        return $theme->renderWorking($page, $church, true);
    }
}

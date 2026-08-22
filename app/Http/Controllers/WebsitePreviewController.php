<?php

namespace App\Http\Controllers;

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
        abort_unless(in_array($page, ['home', 'about', 'leadership', 'ministries', 'contact'], true), 404);

        $church = $tenant->currentChurch();
        abort_if($church === null, 404);
        $settings = $content->settings($church->getKey());
        $theme = $settings ? $themes->resolve((string) $settings->getRawOriginal('theme')) : null;
        abort_if($theme === null, 404);

        return $theme->renderWorking($page, $church, true);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\WebsitePageType;
use App\Models\WebsitePublication;
use App\PublicWebsite\CanonicalChurchWebsiteUrl;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\Themes\ThemeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicWebsiteController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteContext $context,
        private readonly PublicWebsiteContent $content,
        private readonly ThemeRegistry $themes,
        private readonly CanonicalChurchWebsiteUrl $urls,
    ) {}

    /**
     * K-WEB-V1-001D-B §18/§19 — one shared dispatch method behind the
     * existing five named routes (`routes/public-website.php` passes
     * each route's own canonical page value as a route default, so the
     * URLs, route names, and per-page test/reference compatibility are
     * completely unchanged — only the previously-duplicated five
     * one-line methods collapse into this single, registry-driven
     * implementation).
     */
    public function render(Request $request): View|RedirectResponse
    {
        // K-WEB-V1-001D-B — read the route's own bound `page` value
        // explicitly rather than accepting it as a typed method
        // parameter: this route's domain group already binds a `church`
        // parameter ahead of `page` in `$route->parameters()`, and
        // Laravel's controller method injection resolves untyped/
        // primitive parameters *positionally* over that array, not by
        // name — an implicit `string $page` argument would silently
        // receive the church slug instead. Reading it via
        // `$request->route('page')` is unambiguous.
        $page = (string) $request->route('page');
        $pageType = WebsitePageType::tryFrom($page);
        abort_if($pageType === null, 404);

        $publication = $this->publication();
        if ($redirect = $this->aliasRedirect()) {
            return $redirect;
        }
        $theme = $this->themes->resolve($publication->theme);
        abort_if($theme === null, 404);
        // §16/§56 — a canonical page type unsupported by the active
        // theme, or disabled in the *published* snapshot, is publicly
        // unreachable even by direct URL — never a generic CMS fallback.
        abort_unless(in_array($pageType, $theme->supportedPageTypes(), true), 404);
        abort_unless($this->content->pagePubliclyEnabled($publication, $pageType), 404);

        return $theme->renderPublished($page, $publication);
    }

    public function sitemap(): Response|RedirectResponse
    {
        $publication = $this->publication();
        if ($redirect = $this->aliasRedirect()) {
            return $redirect;
        }
        $church = $this->context->church();
        $theme = $this->themes->resolve($publication->theme);
        $supported = $theme?->supportedPageTypes() ?? [];
        // K-WEB-V1-001D-B §55 — sitemap enumeration now derives from the
        // immutable published page configuration (theme-supported,
        // navigable, and enabled *in this publication*), never a
        // hard-coded list and never live working settings.
        $urls = collect(WebsitePageType::navigable())
            ->filter(fn (WebsitePageType $type): bool => in_array($type, $supported, true))
            ->filter(fn (WebsitePageType $type): bool => $this->content->pagePubliclyEnabled($publication, $type))
            ->map(fn (WebsitePageType $type): string => $this->urls->page($church, $type->value));

        return response()
            ->view('public-website.sitemap', compact('urls', 'publication'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response|RedirectResponse
    {
        $this->publication();
        if ($redirect = $this->aliasRedirect()) {
            return $redirect;
        }

        return response("User-agent: *\nAllow: /\nSitemap: ".$this->urls->page($this->context->church())."/sitemap.xml\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function aliasRedirect(): ?RedirectResponse
    {
        if (! ($this->context->resolvedHost()?->isAlias() ?? false)) {
            return null;
        }

        $url = $this->urls->path($this->context->church(), request()->getPathInfo());
        if (request()->getQueryString()) {
            $url .= '?'.request()->getQueryString();
        }

        return redirect()->away($url, 308);
    }

    private function publication(): WebsitePublication
    {
        $settings = $this->content->settings($this->context->churchId());
        $publication = $settings?->currentPublication()
            ->withoutGlobalScope('church_tenant')
            ->where('church_id', $this->context->churchId())
            ->first();
        abort_if($publication === null, 404);

        return $publication;
    }
}

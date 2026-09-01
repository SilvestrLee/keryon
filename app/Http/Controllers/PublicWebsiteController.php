<?php

namespace App\Http\Controllers;

use App\Models\WebsitePublication;
use App\PublicWebsite\CanonicalChurchWebsiteUrl;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\Themes\ThemeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class PublicWebsiteController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteContext $context,
        private readonly PublicWebsiteContent $content,
        private readonly ThemeRegistry $themes,
        private readonly CanonicalChurchWebsiteUrl $urls,
    ) {}

    public function home(): View|RedirectResponse
    {
        return $this->render('home');
    }

    public function about(): View|RedirectResponse
    {
        return $this->render('about');
    }

    public function leadership(): View|RedirectResponse
    {
        return $this->render('leadership');
    }

    public function ministries(): View|RedirectResponse
    {
        return $this->render('ministries');
    }

    public function contact(): View|RedirectResponse
    {
        return $this->render('contact');
    }

    public function sitemap(): Response|RedirectResponse
    {
        $publication = $this->publication();
        if ($redirect = $this->aliasRedirect()) {
            return $redirect;
        }
        $church = $this->context->church();
        $urls = collect(['home', 'about', 'leadership', 'ministries', 'contact'])
            ->map(fn (string $page): string => $this->urls->page($church, $page));

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

    private function render(string $page): View|RedirectResponse
    {
        $publication = $this->publication();
        if ($redirect = $this->aliasRedirect()) {
            return $redirect;
        }
        $theme = $this->themes->resolve($publication->theme);

        abort_if($theme === null, 404);

        return $theme->renderPublished($page, $publication);
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

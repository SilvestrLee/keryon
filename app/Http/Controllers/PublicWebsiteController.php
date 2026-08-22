<?php

namespace App\Http\Controllers;

use App\Models\WebsitePublication;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\PublicWebsiteUrl;
use App\PublicWebsite\Themes\ThemeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PublicWebsiteController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteContext $context,
        private readonly PublicWebsiteContent $content,
        private readonly ThemeRegistry $themes,
        private readonly PublicWebsiteUrl $urls,
    ) {}

    public function home(): View
    {
        return $this->render('home');
    }

    public function about(): View
    {
        return $this->render('about');
    }

    public function leadership(): View
    {
        return $this->render('leadership');
    }

    public function ministries(): View
    {
        return $this->render('ministries');
    }

    public function contact(): View
    {
        return $this->render('contact');
    }

    public function sitemap(): Response
    {
        $publication = $this->publication();
        $church = $this->context->church();
        $urls = collect(['home', 'about', 'leadership', 'ministries', 'contact'])
            ->map(fn (string $page): string => $this->urls->page($church, $page));

        return response()
            ->view('public-website.sitemap', compact('urls', 'publication'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $this->publication();

        return response("User-agent: *\nAllow: /\nSitemap: ".$this->urls->page($this->context->church())."/sitemap.xml\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function render(string $page): View
    {
        $publication = $this->publication();
        $theme = $this->themes->resolve($publication->theme);

        abort_if($theme === null, 404);

        return $theme->renderPublished($page, $publication);
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

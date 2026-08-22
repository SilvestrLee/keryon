<?php

namespace App\PublicWebsite\Themes\Proclaim;

use App\Models\Church;
use App\Models\WebsitePublication;
use App\PublicWebsite\PublicMedia;
use App\PublicWebsite\PublicUrl;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\Themes\ThemeRenderer;
use App\PublicWebsite\WebsiteSeo;
use Illuminate\Contracts\View\View;

class ProclaimTheme implements ThemeRenderer
{
    public function __construct(
        private readonly PublicWebsiteContent $content,
        private readonly PublicMedia $media,
        private readonly PublicUrl $url,
        private readonly WebsiteSeo $seo,
    ) {}

    public function renderWorking(string $page, Church $church, bool $preview = false): View
    {
        $context = new PublicWebsiteContext;
        $context->resolve($church);
        $churchId = $church->getKey();
        $data = $this->content->shared($context);
        $data['page'] = $page;
        $data['settings'] = $this->content->settings($churchId);

        return $this->renderData($page, $churchId, $data, $preview);
    }

    public function renderPublished(string $page, WebsitePublication $publication): View
    {
        return $this->renderData($page, $publication->church_id, $this->content->published($publication), false);
    }

    /** @param array<string, mixed> $data */
    private function renderData(string $page, int $churchId, array $data, bool $preview): View
    {
        $data['page'] = $page;
        $data['preview'] = $preview;

        if ($page === 'home') {
            $data['content'] = array_key_exists('home', $data) ? $data['home'] : $this->content->home($churchId);
            $data['heroCtaUrl'] = $this->url->link($data['content']?->hero_cta_url);
            $data['heroImage'] = $this->media->image(
                $churchId,
                $data['content']?->hero_image_id,
                $data['content']?->hero_image_alt_override,
            );
        } elseif ($page === 'about') {
            $data['content'] = array_key_exists('about', $data) ? $data['about'] : $this->content->about($churchId);
        } elseif ($page === 'contact') {
            $data['content'] = array_key_exists('contact', $data) ? $data['contact'] : $this->content->contact($churchId);
            $data['mapUrl'] = $this->url->external($data['content']?->map_embed_url);
        } elseif ($page === 'leadership') {
            $data['profiles'] = ($data['leadership'] ?? $this->content->leadership($churchId))->map(function ($profile) use ($churchId) {
                $profile->publicImage = $this->media->image($churchId, $profile->photo_id, $profile->photo_alt_override);

                return $profile;
            });
        } elseif ($page === 'ministries') {
            $data['ministries'] = ($data['ministries'] ?? $this->content->ministries($churchId))->map(function ($ministry) use ($churchId) {
                $ministry->publicImage = $this->media->image($churchId, $ministry->image_id, $ministry->image_alt_override);

                return $ministry;
            });
        }

        $data['seo'] = $this->seo->forPage($page, $data, $preview);

        return view("public-website.themes.proclaim.{$page}", $data);
    }
}

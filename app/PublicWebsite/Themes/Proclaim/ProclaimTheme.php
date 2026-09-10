<?php

namespace App\PublicWebsite\Themes\Proclaim;

use App\Enums\WebsitePageType;
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

    /**
     * K-WEB-V1-001D-C §13 — Proclaim now has a real template/render
     * branch for all nine canonical types. Events/Messages/Publications/
     * Giving were deliberately withheld from this list in K-WEB-V1-001D-B
     * until their content models and templates actually existed (§79's
     * "theme-misconfiguration" guard) — that work is complete as of this
     * milestone.
     *
     * @return list<WebsitePageType>
     */
    public function supportedPageTypes(): array
    {
        return [
            WebsitePageType::Home,
            WebsitePageType::About,
            WebsitePageType::Leadership,
            WebsitePageType::Ministries,
            WebsitePageType::Events,
            WebsitePageType::Messages,
            WebsitePageType::Publications,
            WebsitePageType::Giving,
            WebsitePageType::Contact,
        ];
    }

    /** @param array<string, mixed> $data */
    private function renderData(string $page, int $churchId, array $data, bool $preview): View
    {
        $data['page'] = $page;
        $data['preview'] = $preview;
        // K-WEB-V1-001D-B §40 — the one authoritative resolved
        // navigation source. `proclaim-layout.blade.php` no longer
        // maintains its own independent desktop/mobile/footer page
        // arrays — it iterates this ordered list, built once, here, from
        // the effective page configuration (already present on `$data`
        // via `PublicWebsiteContent::shared()`/`published()`) filtered
        // to exactly the page types *this* theme actually supports.
        $data['navigation'] = $this->navigation($data['pageSettings'] ?? []);

        if ($page === 'home') {
            $data['content'] = array_key_exists('home', $data) ? $data['home'] : $this->content->home($churchId);
            $data['heroCtaUrl'] = $this->url->link($data['content']?->hero_cta_url);
            $data['heroImage'] = is_array($data['publicMedia'] ?? null)
                ? $this->media->rendition($churchId, $data['publicMedia']['home.hero'] ?? null, $data['content']?->hero_image_alt_override)
                : $this->media->image($churchId, $data['content']?->hero_image_id, $data['content']?->hero_image_alt_override);
        } elseif ($page === 'about') {
            $data['content'] = array_key_exists('about', $data) ? $data['about'] : $this->content->about($churchId);
        } elseif ($page === 'contact') {
            $data['content'] = array_key_exists('contact', $data) ? $data['contact'] : $this->content->contact($churchId);
            $data['mapUrl'] = $this->url->external($data['content']?->map_embed_url);
        } elseif ($page === 'leadership') {
            $data['profiles'] = ($data['leadership'] ?? $this->content->leadership($churchId))->values()->map(function ($profile, int $index) use ($churchId, $data) {
                $profile->publicImage = is_array($data['publicMedia'] ?? null)
                    ? $this->media->rendition($churchId, $data['publicMedia']["leadership.{$index}.photo"] ?? null, $profile->photo_alt_override)
                    : $this->media->image($churchId, $profile->photo_id, $profile->photo_alt_override);

                return $profile;
            });
        } elseif ($page === 'ministries') {
            $data['ministries'] = ($data['ministries'] ?? $this->content->ministries($churchId))->values()->map(function ($ministry, int $index) use ($churchId, $data) {
                $ministry->publicImage = is_array($data['publicMedia'] ?? null)
                    ? $this->media->rendition($churchId, $data['publicMedia']["ministries.{$index}.image"] ?? null, $ministry->image_alt_override)
                    : $this->media->image($churchId, $ministry->image_id, $ministry->image_alt_override);

                return $ministry;
            });
        } elseif ($page === 'events') {
            // K-WEB-V1-001D-C §21/§83 — the snapshot/live collection is
            // already ordered upcoming-first; this branch only resolves
            // Media per item, exactly like Leadership/Ministries above.
            $data['events'] = ($data['events'] ?? $this->content->events($churchId))->values()->map(function ($event, int $index) use ($churchId, $data) {
                $event->publicImage = is_array($data['publicMedia'] ?? null)
                    ? $this->media->rendition($churchId, $data['publicMedia']["events.{$index}.image"] ?? null, $event->image_alt_override)
                    : $this->media->image($churchId, $event->image_id, $event->image_alt_override);
                $event->ctaUrl = $this->url->external($event->cta_url);

                return $event;
            });
        } elseif ($page === 'messages') {
            $data['messages'] = ($data['messages'] ?? $this->content->messages($churchId))->values()->map(function ($message, int $index) use ($churchId, $data) {
                $message->publicImage = is_array($data['publicMedia'] ?? null)
                    ? $this->media->rendition($churchId, $data['publicMedia']["messages.{$index}.image"] ?? null, $message->image_alt_override)
                    : $this->media->image($churchId, $message->image_id, $message->image_alt_override);
                $message->mediaUrl = $this->url->external($message->media_url);

                return $message;
            });
        } elseif ($page === 'publications') {
            $data['publications'] = ($data['publications'] ?? $this->content->publications($churchId))->values()->map(function ($publication, int $index) use ($churchId, $data) {
                $publication->publicCover = is_array($data['publicMedia'] ?? null)
                    ? $this->media->rendition($churchId, $data['publicMedia']["publications.{$index}.cover"] ?? null, $publication->cover_alt_override)
                    : $this->media->image($churchId, $publication->cover_id, $publication->cover_alt_override);
                $publication->purchaseUrl = $this->url->external($publication->purchase_url);

                return $publication;
            });
        } elseif ($page === 'giving') {
            $data['content'] = array_key_exists('giving', $data) ? $data['giving'] : $this->content->giving($churchId);
            $data['givingImage'] = is_array($data['publicMedia'] ?? null)
                ? $this->media->rendition($churchId, $data['publicMedia']['giving.image'] ?? null, $data['content']?->image_alt_override)
                : $this->media->image($churchId, $data['content']?->image_id, $data['content']?->image_alt_override);
            $data['givingUrl'] = $this->url->external($data['content']?->giving_url);
        }

        $data['seo'] = $this->seo->forPage($page, $data, $preview);

        // K-WEB-V1-001D-B §66/§100 — the one dynamic view-path
        // construction in this class. Safe because both real call sites
        // (`PublicWebsiteController::render()`, `WebsitePreviewController`)
        // already reject any `$page` that fails `WebsitePageType::tryFrom()`
        // or this theme's own `supportedPageTypes()` before ever reaching
        // `renderData()` — `$page` here is always one of the five known,
        // registered values, never raw unvalidated request input.
        return view("public-website.themes.proclaim.{$page}", $data);
    }

    /**
     * K-WEB-V1-001D-B §24/§40 — a page appears in navigation only when
     * it is (a) enabled per the effective page configuration, (b)
     * navigation-capable (`WebsitePageType::navigationCapable()`), and
     * (c) supported by this theme. Ordered by the effective `nav_order`.
     * URL construction itself stays in the Blade layout (it already
     * depends on `$preview`, a presentation concern) — this method's job
     * is only to decide *which* pages appear and in *what order/label*,
     * once, for every nav surface (desktop/mobile/footer) to share.
     *
     * @param  array<string, array{enabled: bool, nav_order: int, navigation_label: ?string}>  $pageSettings
     * @return list<array{key: string, label: string}>
     */
    private function navigation(array $pageSettings): array
    {
        $supported = $this->supportedPageTypes();

        $items = collect(WebsitePageType::navigable())
            ->filter(fn (WebsitePageType $type): bool => in_array($type, $supported, true))
            ->filter(fn (WebsitePageType $type): bool => $pageSettings[$type->value]['enabled'] ?? true)
            ->sortBy(fn (WebsitePageType $type): int => $pageSettings[$type->value]['nav_order'] ?? $type->defaultNavOrder())
            ->map(fn (WebsitePageType $type): array => [
                'key' => $type->value,
                'label' => $pageSettings[$type->value]['navigation_label'] ?? $type->label(),
            ])
            ->values();

        return $items->all();
    }
}

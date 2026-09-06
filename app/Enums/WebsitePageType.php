<?php

namespace App\Enums;

/**
 * K-WEB-V1-001D-B §12/§13 — the canonical, platform-owned Website page
 * registry. Page *identity* belongs to Keryon, never to a theme: this is
 * a closed, code-registered enum, following the exact precedent
 * `WebsiteTheme` already establishes for the same reason ("Keryon-curated
 * ... never user-uploadable"). A theme may declare support for a subset
 * of these cases (`ThemeRenderer::supportedPageTypes()`) and render them
 * however it likes, but it may never redefine what a case *means*, and no
 * code path anywhere registers a new case at runtime — see §15/§65/§100
 * (fail-closed, no arbitrary page-type registration).
 *
 * Only the five page types the Website already ships with today are
 * registered in this milestone (K-WEB-V1-001D-B §3/§12). Events,
 * Messages, Publications, and Giving are recorded architecture for
 * K-WEB-V1-001D-C — see that milestone's own directive — and must not be
 * added as cases here.
 *
 * The enum's `value` is the permanent page key (used in routes, snapshot
 * keys, and `website_page_settings.page_type`) and must never change
 * once shipped. `label()` is the only place a human-facing English word
 * lives — deliberately kept distinct from `value` so a future
 * navigation-label override (`WebsitePageSetting::navigation_label`) and
 * a future localized display can both vary without ever touching page
 * identity (§39/§71 — canonical label vs. navigation label vs.
 * multilingual-future compatibility).
 */
enum WebsitePageType: string
{
    case Home = 'home';
    case About = 'about';
    case Leadership = 'leadership';
    case Ministries = 'ministries';
    case Contact = 'contact';

    /** @return list<self> */
    public static function navigable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->navigationCapable()));
    }

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::About => 'About',
            self::Leadership => 'Leadership',
            self::Ministries => 'Ministries',
            self::Contact => 'Contact',
        };
    }

    /**
     * K-WEB-V1-001D-B §42 — the short description shown on the Website
     * Overview "Site setup" checklist card. Kept here (static, canonical)
     * rather than duplicated in `WebsiteOverview` — the one piece of that
     * card's content that genuinely is page-identity metadata, not a
     * dynamic "started"/"count"/"url" computation (those stay in
     * `WebsiteOverview` itself — see that class's own docblock).
     */
    public function description(): string
    {
        return match ($this) {
            self::Home => 'Hero, welcome message, and scripture highlight.',
            self::About => 'Church story, vision, mission, and leadership introduction.',
            self::Leadership => 'Pastors, ministers, elders, and team profiles.',
            self::Ministries => 'The ministries your church website shows to visitors.',
            self::Contact => 'Office hours and map link, alongside your Church Information.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Home => 'heroicon-o-home',
            self::About => 'heroicon-o-book-open',
            self::Leadership => 'heroicon-o-user-group',
            self::Ministries => 'heroicon-o-heart',
            self::Contact => 'heroicon-o-envelope',
        };
    }

    /**
     * The public URL segment. Home is the one case with no segment of
     * its own (`/`) — every other case's slug is identical to its
     * `value` today, but `slug()` is kept as its own method (rather than
     * always reading `value`) precisely because that will not remain
     * true forever (K-WEB-V1-001D-A §15 — slugs are fixed per page type,
     * not guaranteed identical to the canonical key forever).
     */
    public function slug(): string
    {
        return match ($this) {
            self::Home => '',
            default => $this->value,
        };
    }

    /**
     * `home` and `contact` are the only two v1 page types a Church cannot
     * effectively disable (K-WEB-V1-001D-B §32) — a Website with no
     * homepage or no way to reach the church is not a usable product.
     */
    public function required(): bool
    {
        return match ($this) {
            self::Home, self::Contact => true,
            default => false,
        };
    }

    public function navigationCapable(): bool
    {
        return true;
    }

    public function shape(): WebsitePageShape
    {
        return match ($this) {
            self::Home, self::About, self::Contact => WebsitePageShape::Singleton,
            self::Leadership, self::Ministries => WebsitePageShape::Collection,
        };
    }

    /**
     * Stable default navigation order, deliberately spaced (not 1..5) so
     * a future page type (K-WEB-V1-001D-C) can be inserted between two
     * existing types without renumbering every case — e.g. a future
     * `messages` case could default to 35, between Ministries (30) and
     * Contact (50), without touching this method's existing cases.
     */
    public function defaultNavOrder(): int
    {
        return match ($this) {
            self::Home => 10,
            self::About => 20,
            self::Leadership => 30,
            self::Ministries => 40,
            self::Contact => 50,
        };
    }
}

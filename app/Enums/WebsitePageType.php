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
 * K-WEB-V1-001D-C adds the four page types deliberately deferred from
 * K-WEB-V1-001D-B: Events, Messages, Publications, Giving. All nine
 * cases below are now final for Keryon Website v1 (K-WEB-V1-001D-C §1).
 *
 * The enum's `value` is the permanent page key (used in routes, snapshot
 * keys, and `website_page_settings.page_type`) and must never change
 * once shipped. `label()` is the only place a human-facing English word
 * lives — deliberately kept distinct from `value` so a future
 * navigation-label override (`WebsitePageSetting::navigation_label`) and
 * a future localized display can both vary without ever touching page
 * identity (§39/§71 — canonical label vs. navigation label vs.
 * multilingual-future compatibility). This is exactly how a Church can
 * later display "Sermons" for `messages` or "Books" for `publications`
 * without either canonical key or slug ever changing (K-WEB-V1-001D-C
 * §8/§12).
 */
enum WebsitePageType: string
{
    case Home = 'home';
    case About = 'about';
    case Leadership = 'leadership';
    case Ministries = 'ministries';
    case Events = 'events';
    case Messages = 'messages';
    case Publications = 'publications';
    case Giving = 'giving';
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
            self::Events => 'Events',
            self::Messages => 'Messages',
            self::Publications => 'Publications',
            self::Giving => 'Giving',
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
            self::Events => 'Upcoming events your church wants visitors to know about.',
            self::Messages => 'Sermons and messages visitors can watch or listen to.',
            self::Publications => 'Books and resources your church or pastor has published.',
            self::Giving => 'How visitors can give to your church.',
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
            self::Events => 'heroicon-o-calendar-days',
            self::Messages => 'heroicon-o-play-circle',
            self::Publications => 'heroicon-o-book-open',
            self::Giving => 'heroicon-o-gift',
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
     * K-WEB-V1-001D-C §12 — stable v1 slugs `/events`, `/messages`,
     * `/publications`, `/giving`; never Church-customizable, and never
     * an alternate alias (e.g. no `/sermons`) merely because a Church's
     * navigation label differs.
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
            self::Home, self::About, self::Contact, self::Giving => WebsitePageShape::Singleton,
            self::Leadership, self::Ministries, self::Events, self::Messages, self::Publications => WebsitePageShape::Collection,
        };
    }

    /**
     * Stable default navigation order, deliberately spaced (not 1..5) so
     * a future page type could be inserted between two existing types
     * without renumbering every case. K-WEB-V1-001D-C inserts Events
     * (35), Messages (36), Publications (37), and Giving (38) between
     * Ministries (30) and Contact (50) — the exact gap this spacing was
     * left for — without touching any of the five original values.
     */
    public function defaultNavOrder(): int
    {
        return match ($this) {
            self::Home => 10,
            self::About => 20,
            self::Leadership => 30,
            self::Ministries => 40,
            self::Events => 50,
            self::Messages => 60,
            self::Publications => 70,
            self::Giving => 80,
            self::Contact => 90,
        };
    }

    /**
     * K-WEB-V1-001D-C §10 — the default *effective* enabled state when a
     * Church has no `website_page_settings` row at all for this type.
     * Deliberately distinct from `required()`: a required type is always
     * enabled regardless of any stored value, but this method governs
     * only the *fallback* used for optional types with no row.
     *
     * The five page types that existed before K-WEB-V1-001D-B must keep
     * behaving exactly as they did before that migration — defaulting to
     * enabled — for every already-onboarded Church. The four page types
     * introduced in K-WEB-V1-001D-C must NOT silently appear on every
     * existing (or new) Church's Website the moment this enum gained
     * these cases — they default to disabled until a Church explicitly
     * configures and enables them via Page Settings. This is the one
     * deliberate behavioral difference between the legacy five and the
     * new four; every other resolution rule is identical.
     */
    public function defaultEnabledWhenUnconfigured(): bool
    {
        return match ($this) {
            self::Home, self::About, self::Leadership, self::Ministries, self::Contact => true,
            self::Events, self::Messages, self::Publications, self::Giving => false,
        };
    }
}

<?php

namespace App\PublicWebsite;

use App\Enums\WebsitePageType;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchPublication;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteContactContent;
use App\Models\WebsiteEvent;
use App\Models\WebsiteGivingContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMessage;
use App\Models\WebsiteMinistry;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PublicWebsiteContent
{
    public function __construct(
        private readonly PublicMedia $media,
        private readonly PublicUrl $url,
        private readonly WebsitePageConfiguration $pageConfiguration,
    ) {}

    public function settings(int $churchId): ?WebsiteSettings
    {
        return WebsiteSettings::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->first();
    }

    /** @return array<string, mixed> */
    public function shared(PublicWebsiteContext $context): array
    {
        $church = $context->church();
        $brand = ChurchBrandProfile::withoutGlobalScope('church_tenant')->where('church_id', $church->id)->first();

        return [
            'church' => $church,
            'brand' => $brand,
            'logo' => $this->media->image($church->id, $brand?->primary_logo_media_id, $church->name),
            'mark' => $this->media->image($church->id, $brand?->mark_media_id, ''),
            'serviceTimes' => ChurchServiceTime::withoutGlobalScope('church_tenant')->where('church_id', $church->id)->orderBy('sort_order')->get(),
            'socialLinks' => ChurchSocialLink::withoutGlobalScope('church_tenant')
                ->where('church_id', $church->id)
                ->orderBy('sort_order')
                ->get()
                ->map(function (ChurchSocialLink $social): ChurchSocialLink {
                    $social->publicUrl = $this->url->external($social->url);

                    return $social;
                })
                ->filter(fn (ChurchSocialLink $social): bool => $social->publicUrl !== null),
            'palette' => $this->palette($brand),
            'pageSettings' => $this->pageConfiguration->effectiveForChurch($church->id),
        ];
    }

    public function home(int $churchId): ?WebsiteHomeContent
    {
        return WebsiteHomeContent::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->first();
    }

    public function about(int $churchId): ?WebsiteAboutContent
    {
        return WebsiteAboutContent::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->first();
    }

    public function contact(int $churchId): ?WebsiteContactContent
    {
        return WebsiteContactContent::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->first();
    }

    /** @return Collection<int, WebsiteLeadershipProfile> */
    public function leadership(int $churchId): Collection
    {
        return WebsiteLeadershipProfile::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->orderBy('sort_order')->get();
    }

    /** @return Collection<int, WebsiteMinistry> */
    public function ministries(int $churchId): Collection
    {
        return WebsiteMinistry::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->orderBy('sort_order')->get();
    }

    /**
     * K-WEB-V1-001D-C §83 — deterministic content-layer order:
     * chronological ascending by `starts_at`, with `sort_order` as the
     * secondary tie-breaker for events sharing the same start time. This
     * is content data, not presentation — it deliberately does NOT
     * partition by current/past. `now()` advancing must never change
     * what this method returns for otherwise-unedited content, or a
     * Church's publication would drift into a false "pending changes"
     * state purely from the passage of time (see
     * `WebsiteSnapshot::capture()`'s identical contract for the
     * published/snapshot path).
     *
     * K-PROCLAIM-V1-001C §9 first found that the public Events page
     * rendered past events ahead of upcoming ones; K-PROCLAIM-V1-001C-R
     * corrected the fix's placement — the upcoming-first partition now
     * lives once, at request/render time, in
     * `ProclaimTheme::resolveEvents()`, which both this method's
     * Preview/working-state callers and the published-snapshot path
     * funnel through. This method itself only needs to stay
     * deterministic.
     *
     * @return Collection<int, WebsiteEvent>
     */
    public function events(int $churchId): Collection
    {
        return WebsiteEvent::withoutGlobalScope('church_tenant')
            ->where('church_id', $churchId)
            ->orderBy('starts_at')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * K-WEB-V1-001D-C §84 — newest message date first, with `id`
     * (creation order) as the stable fallback for messages sharing the
     * same date or having none.
     *
     * @return Collection<int, WebsiteMessage>
     */
    public function messages(int $churchId): Collection
    {
        return WebsiteMessage::withoutGlobalScope('church_tenant')
            ->where('church_id', $churchId)
            ->orderByDesc('message_date')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, ChurchPublication> */
    public function publications(int $churchId): Collection
    {
        return ChurchPublication::withoutGlobalScope('church_tenant')
            ->where('church_id', $churchId)
            ->orderBy('sort_order')
            ->get();
    }

    public function giving(int $churchId): ?WebsiteGivingContent
    {
        return WebsiteGivingContent::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->first();
    }

    /** @return array<string, mixed> */
    public function published(WebsitePublication $publication): array
    {
        $snapshot = $publication->snapshot;
        $church = $this->hydrate(Church::class, $snapshot['church']);
        $church->id = $publication->church_id;
        $brand = $this->hydrateNullable(ChurchBrandProfile::class, $snapshot['brand']);
        $hasRenditionMap = array_key_exists('public_media', $snapshot);
        $publicMedia = $snapshot['public_media'] ?? [];

        $socialLinks = $this->collection(ChurchSocialLink::class, $snapshot['social_links'])
            ->map(function (ChurchSocialLink $social): ChurchSocialLink {
                $social->publicUrl = $this->url->external($social->url);

                return $social;
            })
            ->filter(fn (ChurchSocialLink $social): bool => $social->publicUrl !== null);

        return [
            'church' => $church,
            'brand' => $brand,
            'logo' => $hasRenditionMap
                ? $this->media->rendition($publication->church_id, $publicMedia['brand.logo'] ?? null, $church->name)
                : $this->media->image($publication->church_id, $brand?->primary_logo_media_id, $church->name),
            'mark' => $hasRenditionMap
                ? $this->media->rendition($publication->church_id, $publicMedia['brand.mark'] ?? null, '')
                : $this->media->image($publication->church_id, $brand?->mark_media_id, ''),
            'publicMedia' => $hasRenditionMap ? $publicMedia : null,
            'serviceTimes' => $this->collection(ChurchServiceTime::class, $snapshot['service_times']),
            'socialLinks' => $socialLinks,
            'palette' => $this->palette($brand),
            'settings' => $this->hydrate(WebsiteSettings::class, $snapshot['settings']),
            'home' => $this->hydrateNullable(WebsiteHomeContent::class, $snapshot['home']),
            'about' => $this->hydrateNullable(WebsiteAboutContent::class, $snapshot['about']),
            'contact' => $this->hydrateNullable(WebsiteContactContent::class, $snapshot['contact']),
            'leadership' => $this->collection(WebsiteLeadershipProfile::class, $snapshot['leadership']),
            'ministries' => $this->collection(WebsiteMinistry::class, $snapshot['ministries']),
            // K-WEB-V1-001D-C §67/§118 — a publication made before this
            // milestone has none of these four keys at all; `?? []`/
            // `?? null` resolve them to the same empty state a Church
            // with no content of that type already produces, so an old
            // snapshot renders exactly as it always did.
            'events' => $this->collection(WebsiteEvent::class, $snapshot['events'] ?? []),
            'messages' => $this->collection(WebsiteMessage::class, $snapshot['messages'] ?? []),
            'publications' => $this->collection(ChurchPublication::class, $snapshot['publications'] ?? []),
            'giving' => $this->hydrateNullable(WebsiteGivingContent::class, $snapshot['giving'] ?? null),
            // K-WEB-V1-001D-B §50/§51 — `$snapshot['page_settings']` is
            // absent on every historical publication made before this
            // milestone; `effectiveFromSnapshot(null)` resolves the exact
            // same legacy "everything enabled, default order, no label
            // override" defaults `effectiveForChurch()` already resolves
            // for a Church with zero `website_page_settings` rows, so an
            // old publication renders identically to how it always did.
            'pageSettings' => $this->pageConfiguration->effectiveFromSnapshot($snapshot['page_settings'] ?? null),
        ];
    }

    /**
     * K-WEB-V1-001D-B §56 — a cheap, standalone check the public
     * controller uses to decide whether a requested page type is
     * publicly reachable under *this* publication, without hydrating the
     * full `published()` content array. Reads only the immutable
     * snapshot's own stored page-settings — never the live working
     * `website_page_settings` table.
     */
    public function pagePubliclyEnabled(WebsitePublication $publication, WebsitePageType $type): bool
    {
        $effective = $this->pageConfiguration->effectiveFromSnapshot($publication->snapshot['page_settings'] ?? null);

        return $effective[$type->value]['enabled'] ?? true;
    }

    /** @param class-string<Model> $class */
    private function hydrate(string $class, array $attributes): Model
    {
        return (new $class)->forceFill($attributes);
    }

    /** @param class-string<Model> $class */
    private function hydrateNullable(string $class, ?array $attributes): ?Model
    {
        return $attributes === null ? null : $this->hydrate($class, $attributes);
    }

    /** @param class-string<Model> $class */
    private function collection(string $class, array $records): Collection
    {
        return new Collection(array_map(fn (array $record): Model => $this->hydrate($class, $record), $records));
    }

    /** @return array{accent: string, heading: string, body: string} */
    private function palette(?ChurchBrandProfile $brand): array
    {
        $accent = collect([$brand?->primary_color, $brand?->secondary_color, $brand?->accent_color])
            ->first(fn (?string $color): bool => $color !== null && $this->contrastAgainstWhite($color) >= 4.5)
            ?? '#315C4A';

        return [
            'accent' => $accent,
            'heading' => $this->fontStack($brand?->heading_font?->value),
            'body' => $this->fontStack($brand?->body_font?->value),
        ];
    }

    private function fontStack(?string $font): string
    {
        return match ($font) {
            'playfair_display', 'merriweather', 'source_serif' => "Georgia, 'Times New Roman', serif",
            'geist' => "'Avenir Next', Avenir, 'Segoe UI', sans-serif",
            default => "Inter, 'Segoe UI', sans-serif",
        };
    }

    private function contrastAgainstWhite(string $hex): float
    {
        $channels = array_map(
            fn (string $pair): float => hexdec($pair) / 255,
            str_split(substr($hex, 1), 2),
        );
        $channels = array_map(
            fn (float $value): float => $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4,
            $channels,
        );
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return 1.05 / ($luminance + 0.05);
    }
}

<?php

namespace App\PublicWebsite;

use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteContactContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
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

    /** @return array<string, mixed> */
    public function published(WebsitePublication $publication): array
    {
        $snapshot = $publication->snapshot;
        $church = $this->hydrate(Church::class, $snapshot['church']);
        $church->id = $publication->church_id;
        $brand = $this->hydrateNullable(ChurchBrandProfile::class, $snapshot['brand']);

        $socialLinks = $this->collection(ChurchSocialLink::class, $snapshot['social_links'])
            ->map(function (ChurchSocialLink $social): ChurchSocialLink {
                $social->publicUrl = $this->url->external($social->url);

                return $social;
            })
            ->filter(fn (ChurchSocialLink $social): bool => $social->publicUrl !== null);

        return [
            'church' => $church,
            'brand' => $brand,
            'logo' => $this->media->image($publication->church_id, $brand?->primary_logo_media_id, $church->name),
            'mark' => $this->media->image($publication->church_id, $brand?->mark_media_id, ''),
            'serviceTimes' => $this->collection(ChurchServiceTime::class, $snapshot['service_times']),
            'socialLinks' => $socialLinks,
            'palette' => $this->palette($brand),
            'settings' => $this->hydrate(WebsiteSettings::class, $snapshot['settings']),
            'home' => $this->hydrateNullable(WebsiteHomeContent::class, $snapshot['home']),
            'about' => $this->hydrateNullable(WebsiteAboutContent::class, $snapshot['about']),
            'contact' => $this->hydrateNullable(WebsiteContactContent::class, $snapshot['contact']),
            'leadership' => $this->collection(WebsiteLeadershipProfile::class, $snapshot['leadership']),
            'ministries' => $this->collection(WebsiteMinistry::class, $snapshot['ministries']),
        ];
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

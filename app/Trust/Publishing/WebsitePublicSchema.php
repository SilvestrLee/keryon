<?php

namespace App\Trust\Publishing;

use App\Enums\WebsitePageType;
use Illuminate\Validation\ValidationException;

class WebsitePublicSchema
{
    /** @var array<string, list<string>> */
    private const FIELDS = [
        'church' => ['name', 'slug', 'email', 'phone', 'address'],
        'brand' => ['primary_color', 'secondary_color', 'accent_color', 'heading_font', 'body_font'],
        'settings' => ['footer_note'],
        'home' => [
            'hero_heading', 'hero_subheading', 'hero_cta_label', 'hero_cta_url',
            'hero_image_alt_override', 'welcome_heading', 'welcome_body',
            'scripture_reference', 'scripture_text',
        ],
        'about' => ['church_story', 'vision', 'mission', 'leadership_introduction'],
        'contact' => ['office_hours', 'map_embed_url'],
        'leadership' => ['name', 'category', 'role_title', 'bio', 'photo_alt_override', 'sort_order'],
        'ministries' => ['name', 'description', 'image_alt_override', 'sort_order'],
        // K-WEB-V1-001D-C §62 — explicit allow-lists, exactly like every
        // other section. Never `church_id`, never a raw Media ID (those
        // are stripped by `WebsitePublisher::withoutPrivateMediaIds()`
        // before this gate ever runs), never a timestamp not needed
        // publicly.
        'events' => [
            'title', 'summary', 'starts_at', 'ends_at', 'venue',
            'image_alt_override', 'cta_label', 'cta_url', 'is_featured', 'sort_order',
        ],
        'messages' => [
            'title', 'speaker', 'message_date', 'scripture_reference', 'summary',
            'image_alt_override', 'media_url', 'is_featured',
        ],
        'publications' => [
            'title', 'author', 'publication_type', 'description',
            'cover_alt_override', 'price_text', 'purchase_url', 'is_featured', 'sort_order',
        ],
        'giving' => ['headline', 'body', 'image_alt_override', 'cta_label', 'giving_url', 'additional_instructions'],
        'service_times' => ['label', 'day_of_week', 'time', 'sort_order'],
        'social_links' => ['platform', 'url', 'sort_order'],
    ];

    /** @param array<string, mixed> $snapshot */
    public function ensure(array $snapshot): void
    {
        // K-WEB-V1-001D-B §49 — `page_settings` is now a recognized,
        // trust-gated top-level snapshot key, validated below with the
        // same fail-closed discipline as every other section.
        $allowedTopLevel = [...array_keys(self::FIELDS), 'public_media', 'page_settings'];
        $this->ensureKeys($snapshot, $allowedTopLevel);
        $this->ensurePageSettings($snapshot['page_settings'] ?? null);

        foreach (self::FIELDS as $section => $fields) {
            $value = $snapshot[$section] ?? null;

            if (in_array($section, ['leadership', 'ministries', 'events', 'messages', 'publications', 'service_times', 'social_links'], true)) {
                if (! is_array($value)) {
                    $this->deny();
                }

                foreach ($value as $record) {
                    if (! is_array($record)) {
                        $this->deny();
                    }
                    $this->ensureKeys($record, $fields);
                }

                continue;
            }

            if ($value !== null) {
                if (! is_array($value)) {
                    $this->deny();
                }
                $this->ensureKeys($value, $fields);
            }
        }

        foreach ($snapshot['public_media'] ?? [] as $usage => $uuid) {
            if (! is_string($usage)
                || ! preg_match('/^(brand\.(logo|mark)|home\.hero|leadership\.\d+\.photo|ministries\.\d+\.image|events\.\d+\.image|messages\.\d+\.image|publications\.\d+\.cover|giving\.image)$/', $usage)
                || ! is_string($uuid)
                || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
                $this->deny();
            }
        }
    }

    /** @param array<string, mixed> $values @param list<string> $allowed */
    private function ensureKeys(array $values, array $allowed): void
    {
        if (array_diff(array_keys($values), $allowed) !== []) {
            $this->deny();
        }
    }

    /**
     * K-WEB-V1-001D-B §49 — every key must be a real, registered
     * `WebsitePageType` value (never an arbitrary string a future bug
     * could smuggle through), and every value must be exactly the three
     * fields `WebsitePageConfiguration` ever produces — no free-form
     * page configuration of any kind reaches a public snapshot.
     */
    private function ensurePageSettings(mixed $pageSettings): void
    {
        if ($pageSettings === null) {
            return;
        }

        if (! is_array($pageSettings)) {
            $this->deny();
        }

        foreach ($pageSettings as $key => $value) {
            if (! is_string($key) || WebsitePageType::tryFrom($key) === null || ! is_array($value)) {
                $this->deny();
            }
            $this->ensureKeys($value, ['enabled', 'nav_order', 'navigation_label']);

            if (! is_bool($value['enabled'] ?? null) || ! is_int($value['nav_order'] ?? null)) {
                $this->deny();
            }
            if (array_key_exists('navigation_label', $value) && $value['navigation_label'] !== null && ! is_string($value['navigation_label'])) {
                $this->deny();
            }
        }
    }

    private function deny(): never
    {
        throw ValidationException::withMessages([
            'publication' => 'The Website contains information that is not part of the approved public schema.',
        ]);
    }
}

<?php

namespace App\Trust\Publishing;

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
        'service_times' => ['label', 'day_of_week', 'time', 'sort_order'],
        'social_links' => ['platform', 'url', 'sort_order'],
    ];

    /** @param array<string, mixed> $snapshot */
    public function ensure(array $snapshot): void
    {
        $allowedTopLevel = [...array_keys(self::FIELDS), 'public_media'];
        $this->ensureKeys($snapshot, $allowedTopLevel);

        foreach (self::FIELDS as $section => $fields) {
            $value = $snapshot[$section] ?? null;

            if (in_array($section, ['leadership', 'ministries', 'service_times', 'social_links'], true)) {
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
                || ! preg_match('/^(brand\.(logo|mark)|home\.hero|leadership\.\d+\.photo|ministries\.\d+\.image)$/', $usage)
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

    private function deny(): never
    {
        throw ValidationException::withMessages([
            'publication' => 'The Website contains information that is not part of the approved public schema.',
        ]);
    }
}

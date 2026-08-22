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
use App\Models\WebsiteSettings;
use Illuminate\Database\Eloquent\Model;

class WebsiteSnapshot
{
    /** @return array<string, mixed> */
    public function capture(Church $church, WebsiteSettings $settings): array
    {
        $churchId = $church->getKey();

        return [
            'church' => $church->only(['name', 'slug', 'email', 'phone', 'address']),
            'brand' => $this->one(ChurchBrandProfile::class, $churchId, [
                'primary_logo_media_id', 'mark_media_id', 'primary_color', 'secondary_color',
                'accent_color', 'heading_font', 'body_font',
            ]),
            'settings' => ['footer_note' => $settings->footer_note],
            'home' => $this->one(WebsiteHomeContent::class, $churchId, [
                'hero_heading', 'hero_subheading', 'hero_cta_label', 'hero_cta_url', 'hero_image_id',
                'hero_image_alt_override', 'welcome_heading', 'welcome_body', 'scripture_reference', 'scripture_text',
            ]),
            'about' => $this->one(WebsiteAboutContent::class, $churchId, [
                'church_story', 'vision', 'mission', 'leadership_introduction',
            ]),
            'contact' => $this->one(WebsiteContactContent::class, $churchId, ['office_hours', 'map_embed_url']),
            'leadership' => $this->many(WebsiteLeadershipProfile::class, $churchId, [
                'name', 'category', 'role_title', 'bio', 'photo_id', 'photo_alt_override', 'sort_order',
            ]),
            'ministries' => $this->many(WebsiteMinistry::class, $churchId, [
                'name', 'description', 'image_id', 'image_alt_override', 'sort_order',
            ]),
            'service_times' => $this->many(ChurchServiceTime::class, $churchId, ['label', 'day_of_week', 'time', 'sort_order']),
            'social_links' => $this->many(ChurchSocialLink::class, $churchId, ['platform', 'url', 'sort_order']),
        ];
    }

    /** @param class-string<Model> $model */
    private function one(string $model, int $churchId, array $fields): ?array
    {
        return $model::withoutGlobalScope('church_tenant')->where('church_id', $churchId)->first()?->only($fields);
    }

    /** @param class-string<Model> $model */
    private function many(string $model, int $churchId, array $fields): array
    {
        return $model::withoutGlobalScope('church_tenant')
            ->where('church_id', $churchId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($record): array => $record->only($fields))
            ->values()
            ->all();
    }

    public function fingerprint(array $snapshot, string $theme): string
    {
        return hash('sha256', json_encode(['theme' => $theme, 'snapshot' => $snapshot], JSON_THROW_ON_ERROR));
    }
}

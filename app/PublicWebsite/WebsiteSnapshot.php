<?php

namespace App\PublicWebsite;

use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\MediaAsset;
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

    /**
     * K-WEB-V1-001B §4 — the single canonical fingerprint path. Every
     * caller (publication itself, and any later "has anything changed"
     * recomputation) passes the *raw* `capture()` output here and gets
     * back a fingerprint of the same canonical representation — there is
     * no second, approximately-equivalent transformation for callers to
     * keep in sync by hand. Callers never need to know that private
     * Media identity is normalized before hashing.
     */
    public function fingerprint(array $snapshot, string $theme): string
    {
        return hash('sha256', json_encode(['theme' => $theme, 'snapshot' => $this->canonicalize($snapshot)], JSON_THROW_ON_ERROR));
    }

    /**
     * Replaces every private Media database ID in the snapshot with that
     * asset's own stable `sha256` content hash, never the mutable row ID
     * and never a public rendition UUID. This is deliberately a pure,
     * read-only, side-effect-free substitution — computing a fingerprint
     * (including the "has anything changed since publication" check)
     * must never create a public rendition merely to find out. The
     * publication's own stored `snapshot` column is unaffected by this
     * method — it continues to carry the real `public_media` renditions
     * needed for public rendering; only the fingerprint input changes.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function canonicalize(array $snapshot): array
    {
        if (isset($snapshot['brand']) && is_array($snapshot['brand'])) {
            $snapshot['brand']['primary_logo_media_id'] = $this->mediaIdentity($snapshot['brand']['primary_logo_media_id'] ?? null);
            $snapshot['brand']['mark_media_id'] = $this->mediaIdentity($snapshot['brand']['mark_media_id'] ?? null);
        }

        if (isset($snapshot['home']) && is_array($snapshot['home'])) {
            $snapshot['home']['hero_image_id'] = $this->mediaIdentity($snapshot['home']['hero_image_id'] ?? null);
        }

        foreach ($snapshot['leadership'] ?? [] as $index => $profile) {
            $snapshot['leadership'][$index]['photo_id'] = $this->mediaIdentity($profile['photo_id'] ?? null);
        }

        foreach ($snapshot['ministries'] ?? [] as $index => $ministry) {
            $snapshot['ministries'][$index]['image_id'] = $this->mediaIdentity($ministry['image_id'] ?? null);
        }

        return $snapshot;
    }

    /**
     * `null` means "no Media selected" and must stay distinct from "a
     * Media ID was selected but its content identity could not be
     * resolved" (e.g. a legacy asset with no recorded hash, or one that
     * no longer exists) — the latter still carries the original ID
     * forward as a fallback so it remains distinguishable from an
     * unrelated missing asset, rather than silently collapsing every
     * unresolvable reference to the same `null`.
     */
    private function mediaIdentity(?int $mediaAssetId): ?string
    {
        if ($mediaAssetId === null) {
            return null;
        }

        $sha256 = MediaAsset::withoutGlobalScopes()->withTrashed()->find($mediaAssetId)?->sha256;

        return $sha256 ?? "unresolved:{$mediaAssetId}";
    }
}

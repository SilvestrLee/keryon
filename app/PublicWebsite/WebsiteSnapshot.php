<?php

namespace App\PublicWebsite;

use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchPublication;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\MediaAsset;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteContactContent;
use App\Models\WebsiteEvent;
use App\Models\WebsiteGivingContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMessage;
use App\Models\WebsiteMinistry;
use App\Models\WebsiteSettings;
use Illuminate\Database\Eloquent\Model;

class WebsiteSnapshot
{
    public function __construct(private readonly WebsitePageConfiguration $pageConfiguration) {}

    /** @return array<string, mixed> */
    public function capture(Church $church, WebsiteSettings $settings): array
    {
        $churchId = $church->getKey();

        return [
            // K-WEB-V1-001D-B §49 — the effective page configuration
            // (enabled/order/navigation-label per canonical page type) is
            // publication-relevant state, captured exactly like every
            // other Website content concept: it becomes part of this
            // immutable snapshot at publish time, and public rendering
            // must consult this stored copy, never the live
            // `website_page_settings` table. No Media identity is
            // involved, so this key needs no entry in `canonicalize()`
            // below — it already participates in the fingerprint as-is
            // via the surrounding `json_encode()`.
            'page_settings' => $this->pageConfiguration->effectiveForChurch($churchId),
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
            // K-WEB-V1-001D-C §61, revised by K-PROCLAIM-V1-001C-R §3 —
            // same generic one()/many() pattern; deliberately captures
            // deterministic chronological order only. The
            // current/upcoming-vs-past presentation partition must NOT
            // live here: the snapshot must stay immutable and
            // reproducible from editorial state alone, never dependent
            // on what `now()` happens to be at capture time — otherwise
            // recapturing this same, unedited content later (as
            // `WebsitePublicationStatus::current()` does on every
            // "pending changes" check) would compute a different array
            // order than what was actually published, producing a
            // different fingerprint and a false "pending" state purely
            // from the clock advancing. Presentation-time ordering is
            // applied once, downstream, in
            // `ProclaimTheme::resolveEvents()`.
            'events' => $this->many(WebsiteEvent::class, $churchId, [
                'title', 'summary', 'starts_at', 'ends_at', 'venue',
                'image_id', 'image_alt_override', 'cta_label', 'cta_url', 'is_featured', 'sort_order',
            ], [['starts_at', 'asc'], ['sort_order', 'asc']]),
            'messages' => $this->many(WebsiteMessage::class, $churchId, [
                'title', 'speaker', 'message_date', 'scripture_reference', 'summary',
                'image_id', 'image_alt_override', 'media_url', 'is_featured',
            ], [['message_date', 'desc'], ['id', 'desc']]),
            'publications' => $this->many(ChurchPublication::class, $churchId, [
                'title', 'author', 'publication_type', 'description',
                'cover_id', 'cover_alt_override', 'price_text', 'purchase_url', 'is_featured', 'sort_order',
            ]),
            'giving' => $this->one(WebsiteGivingContent::class, $churchId, [
                'headline', 'body', 'image_id', 'image_alt_override', 'cta_label', 'giving_url', 'additional_instructions',
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

    /**
     * K-WEB-V1-001D-C §61/§83/§84 — `$orderBy` defaults to the existing
     * `sort_order`-only behavior (every K-WEB-V1-001D-B-era caller is
     * unaffected), but now accepts an explicit ordered list of
     * `[column, direction]` pairs so a collection whose *deterministic
     * content* ordering genuinely isn't manual (Events: `starts_at`
     * ascending; Messages: newest `message_date` first) can capture its
     * snapshot in that same order, since `PublicWebsiteContent::
     * published()` renders a snapshot's stored array order as-is.
     *
     * K-PROCLAIM-V1-001C-R §3 — this is deliberately a *content*
     * ordering only. Any presentation-time rule that depends on `now()`
     * (such as Events' current/upcoming-vs-past partition) must never be
     * folded into this method or into `capture()` — it belongs
     * downstream, in the theme's own render path
     * (`ProclaimTheme::resolveEvents()`), which runs fresh on every
     * request rather than being frozen at publish time.
     *
     * @param  class-string<Model>  $model
     * @param  list<array{0: string, 1: string}>  $orderBy
     */
    private function many(string $model, int $churchId, array $fields, array $orderBy = [['sort_order', 'asc']]): array
    {
        $query = $model::withoutGlobalScope('church_tenant')->where('church_id', $churchId);

        foreach ($orderBy as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        return $query->get()
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

        // K-WEB-V1-001D-C §65 — the same content-identity substitution,
        // extended to the four new Media-bearing fields. No new
        // fingerprint mechanism — this is the one existing canonicalize
        // path, mechanically extended.
        foreach ($snapshot['events'] ?? [] as $index => $event) {
            $snapshot['events'][$index]['image_id'] = $this->mediaIdentity($event['image_id'] ?? null);
        }

        foreach ($snapshot['messages'] ?? [] as $index => $message) {
            $snapshot['messages'][$index]['image_id'] = $this->mediaIdentity($message['image_id'] ?? null);
        }

        foreach ($snapshot['publications'] ?? [] as $index => $publication) {
            $snapshot['publications'][$index]['cover_id'] = $this->mediaIdentity($publication['cover_id'] ?? null);
        }

        if (isset($snapshot['giving']) && is_array($snapshot['giving'])) {
            $snapshot['giving']['image_id'] = $this->mediaIdentity($snapshot['giving']['image_id'] ?? null);
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

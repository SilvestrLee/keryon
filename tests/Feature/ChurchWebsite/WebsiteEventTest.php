<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\AssetRightsStatus;
use App\Enums\AssetUse;
use App\Enums\ChurchRole;
use App\Enums\WebsitePageType;
use App\Filament\Clusters\Website\Resources\WebsiteEventResource\Pages\ListWebsiteEvents;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\MediaAssetRights;
use App\Models\User;
use App\Models\WebsiteEvent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePageSetting;
use App\Models\WebsiteSettings;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-C §17-24/§78/§104 — Events: informational Website
 * publishing only. No registration/RSVP/ticketing/attendance/capacity/
 * check-in field exists anywhere in this model, form, or test.
 */
class WebsiteEventTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $this->church = Church::create(['name' => 'Event Test Church', 'slug' => 'event-test-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
    }

    private function image(): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put($path, $bytes);
        $asset = new MediaAsset(['disk' => 'public', 'path' => $path, 'original_filename' => 'event.png', 'mime_type' => 'image/png', 'size' => strlen($bytes), 'width' => 1, 'height' => 1]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    // ---------------------------------------------------------------
    // CRUD / tenancy / authorization
    // ---------------------------------------------------------------

    public function test_an_event_can_be_created_edited_and_deleted(): void
    {
        $event = WebsiteEvent::create(['title' => 'Fall Retreat', 'starts_at' => now()->addWeek()]);
        $this->assertDatabaseHas('website_events', ['id' => $event->id, 'church_id' => $this->church->id]);

        $event->update(['title' => 'Fall Retreat 2026']);
        $this->assertSame('Fall Retreat 2026', $event->fresh()->title);

        $event->delete();
        $this->assertSoftDeleted('website_events', ['id' => $event->id]);
    }

    public function test_cross_church_isolation(): void
    {
        WebsiteEvent::create(['title' => 'Church A Event', 'starts_at' => now()->addWeek()]);

        $otherChurch = Church::create(['name' => 'Other Church', 'slug' => 'event-other-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->assertSame(0, WebsiteEvent::query()->count());
    }

    public function test_communications_can_manage_events_care_cannot(): void
    {
        $this->assertTrue(Gate::allows('viewAny', WebsiteEvent::class));

        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(Gate::allows('viewAny', WebsiteEvent::class));
        $this->assertFalse(Gate::allows('create', WebsiteEvent::class));
    }

    // ---------------------------------------------------------------
    // Validation (§78)
    // ---------------------------------------------------------------

    public function test_title_is_required_via_the_management_form(): void
    {
        Livewire::test(ListWebsiteEvents::class)
            ->callAction('create', data: ['starts_at' => now()->addWeek()->toDateTimeString()])
            ->assertHasActionErrors(['title' => 'required']);
    }

    public function test_end_date_cannot_be_before_start_date_via_the_management_form(): void
    {
        Livewire::test(ListWebsiteEvents::class)
            ->callAction('create', data: [
                'title' => 'Bad Event',
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'ends_at' => now()->toDateTimeString(),
            ])
            ->assertHasActionErrors(['ends_at' => 'after']);
    }

    public function test_unsafe_cta_url_protocol_is_rejected_by_form_validation(): void
    {
        Livewire::test(ListWebsiteEvents::class)
            ->callAction('create', data: [
                'title' => 'Unsafe CTA',
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'cta_url' => 'javascript:alert(1)',
            ])
            ->assertHasActionErrors(['cta_url']);
    }

    public function test_a_cross_church_media_asset_cannot_be_assigned_as_the_event_image(): void
    {
        $otherChurch = Church::create(['name' => 'Foreign Church', 'slug' => 'event-foreign-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        $foreignImage = $this->image();

        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->expectException(\LogicException::class);
        WebsiteEvent::create(['title' => 'x', 'starts_at' => now()->addWeek(), 'image_id' => $foreignImage->id]);
    }

    // ---------------------------------------------------------------
    // Sorting / upcoming behavior (§21/§83)
    // ---------------------------------------------------------------

    public function test_events_content_layer_returns_deterministic_chronological_order(): void
    {
        // K-PROCLAIM-V1-001C §9 first found (and this test, under its
        // old name, briefly asserted) that the public Events page
        // rendered past events ahead of upcoming ones. K-PROCLAIM-
        // V1-001C-R corrected *where* that fix lives: this content-layer
        // method must stay a plain, time-independent chronological
        // order — never partitioned by current/past — so that
        // recomputing it later for the exact same, unedited content
        // (e.g. `WebsitePublicationStatus::current()`'s fingerprint
        // check, which calls `WebsiteSnapshot::capture()` fresh on every
        // "pending changes" check) can never produce a different result
        // merely because `now()` moved on. The upcoming-first
        // presentation guarantee for visitors now lives one layer up, in
        // `ProclaimTheme::resolveEvents()` — proved end-to-end below and
        // in the dedicated time-advance regression test.
        WebsiteEvent::create(['title' => 'Later', 'starts_at' => now()->addMonth()]);
        WebsiteEvent::create(['title' => 'Sooner', 'starts_at' => now()->addDay()]);
        WebsiteEvent::create(['title' => 'Past', 'starts_at' => now()->subMonth()]);

        $ordered = app(PublicWebsiteContent::class)->events($this->church->id);

        $this->assertSame(['Past', 'Sooner', 'Later'], $ordered->pluck('title')->all());
    }

    public function test_past_events_remain_visible_but_render_after_every_upcoming_event_on_the_public_page(): void
    {
        // K-PROCLAIM-V1-001C §9 explicitly forbids silently converting
        // this to a future-only filter (that is Home's teaser
        // behavior, not the dedicated page's) — past events must still
        // appear on /events, just never ahead of upcoming ones. Proved
        // end-to-end against the real anonymous public page, not just
        // the content-layer collection.
        WebsiteEvent::create(['title' => 'Ancient History', 'starts_at' => now()->subYear()]);
        WebsiteEvent::create(['title' => 'Next Up', 'starts_at' => now()->addWeek()]);
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'x']);
        WebsitePageSetting::updateOrCreate(['page_type' => WebsitePageType::Events->value], ['enabled' => true]);
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();
        $response = $this->get("http://{$this->church->slug}.keryon.app/events");

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Ancient History', $body, 'Past events must still be visible, not hidden.');
        $this->assertTrue(
            strpos($body, 'Next Up') < strpos($body, 'Ancient History'),
            'An upcoming event must render before a past event.'
        );
    }

    // ---------------------------------------------------------------
    // Time-aware presentation ordering (K-PROCLAIM-V1-001C-R) — proves
    // ordering is computed at request/render time, not frozen at the
    // moment of publication.
    // ---------------------------------------------------------------

    public function test_public_events_page_reorders_as_time_passes_without_a_republish(): void
    {
        $t1 = Carbon::parse('2026-01-01 09:00:00');
        $this->travelTo($t1);

        // Event A: still open at T1 (ends two hours later), so it is
        // classified current/upcoming at publish time.
        WebsiteEvent::create(['title' => 'Event A', 'starts_at' => $t1->copy()->subHour(), 'ends_at' => $t1->copy()->addHours(2)]);
        // Event B: genuinely upcoming, much further out.
        WebsiteEvent::create(['title' => 'Event B', 'starts_at' => $t1->copy()->addDays(10)]);
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'x']);
        WebsitePageSetting::updateOrCreate(['page_type' => WebsitePageType::Events->value], ['enabled' => true]);
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();
        $atT1 = $this->get("http://{$this->church->slug}.keryon.app/events");
        $atT1->assertOk()->assertSee('Event A')->assertSee('Event B');
        $bodyAtT1 = $atT1->getContent();
        $this->assertTrue(
            strpos($bodyAtT1, 'Event A') < strpos($bodyAtT1, 'Event B'),
            'At T1, Event A (still open, ends later) must lead — it is the sooner-classified current event.'
        );

        // Advance real test time past Event A's end, but nowhere near
        // Event B's start. Deliberately no republish.
        $t2 = $t1->copy()->addHours(4);
        $this->travelTo($t2);

        $atT2 = $this->get("http://{$this->church->slug}.keryon.app/events");
        $atT2->assertOk();
        $bodyAtT2 = $atT2->getContent();

        $this->assertStringContainsString('Event A', $bodyAtT2, 'A past event must remain visible, never hidden.');
        $this->assertTrue(
            strpos($bodyAtT2, 'Event B') < strpos($bodyAtT2, 'Event A'),
            'At T2, with no republish, Event B must now render before Event A purely because real time advanced.'
        );

        $this->travelBack();
    }

    public function test_preview_events_page_reorders_as_time_passes_without_data_mutation(): void
    {
        // Same clock-advance scenario as the public-page test above,
        // proving the single presentation-ordering rule in
        // `ProclaimTheme::resolveEvents()` serves both the
        // published-snapshot source AND the live/working-state source
        // Preview reads from — not two independently-behaving copies.
        $t1 = Carbon::parse('2026-01-01 09:00:00');
        $this->travelTo($t1);

        WebsiteEvent::create(['title' => 'Event A', 'starts_at' => $t1->copy()->subHour(), 'ends_at' => $t1->copy()->addHours(2)]);
        WebsiteEvent::create(['title' => 'Event B', 'starts_at' => $t1->copy()->addDays(10)]);
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsitePageSetting::updateOrCreate(['page_type' => WebsitePageType::Events->value], ['enabled' => true]);

        $previewAtT1 = $this->get('http://localhost/admin/website/preview/events');
        $previewAtT1->assertOk();
        $bodyAtT1 = $previewAtT1->getContent();
        $this->assertTrue(
            strpos($bodyAtT1, 'Event A') < strpos($bodyAtT1, 'Event B'),
            'Preview at T1 must show the same current-first ordering as the public page.'
        );

        $t2 = $t1->copy()->addHours(4);
        $this->travelTo($t2);

        // No content mutation — only the clock moved.
        $previewAtT2 = $this->get('http://localhost/admin/website/preview/events');
        $previewAtT2->assertOk();
        $bodyAtT2 = $previewAtT2->getContent();

        $this->assertStringContainsString('Event A', $bodyAtT2, 'A past event must remain visible in Preview too.');
        $this->assertTrue(
            strpos($bodyAtT2, 'Event B') < strpos($bodyAtT2, 'Event A'),
            'Preview must reorder purely from the clock advancing, with zero data mutation between requests.'
        );

        $this->travelBack();
    }

    // ---------------------------------------------------------------
    // Media rights gate (§64/§112) — reuses the existing publication
    // trust boundary; no favicon-specific/event-specific rights logic.
    // ---------------------------------------------------------------

    public function test_a_rights_restricted_event_image_blocks_publication(): void
    {
        $image = $this->image();
        MediaAssetRights::create([
            'church_id' => $this->church->id,
            'media_asset_id' => $image->id,
            'provenance' => 'church_declared',
            'status' => AssetRightsStatus::Restricted,
            'allowed_uses' => [AssetUse::Store->value, AssetUse::InternalUse->value],
            'restriction_reason' => 'Under review.',
            'attribution_required' => false,
            'declared_at' => now(),
        ]);
        WebsiteEvent::create(['title' => 'Restricted', 'starts_at' => now()->addWeek(), 'image_id' => $image->id]);
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'x']);

        $this->expectException(ValidationException::class);
        app(WebsitePublisher::class)->publish();
    }
}

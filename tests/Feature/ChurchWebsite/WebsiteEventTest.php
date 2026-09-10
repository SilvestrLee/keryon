<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\AssetRightsStatus;
use App\Enums\AssetUse;
use App\Enums\ChurchRole;
use App\Filament\Clusters\Website\Resources\WebsiteEventResource\Pages\ListWebsiteEvents;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\MediaAssetRights;
use App\Models\User;
use App\Models\WebsiteEvent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_events_are_captured_upcoming_first_by_starts_at(): void
    {
        WebsiteEvent::create(['title' => 'Later', 'starts_at' => now()->addMonth()]);
        WebsiteEvent::create(['title' => 'Sooner', 'starts_at' => now()->addDay()]);
        WebsiteEvent::create(['title' => 'Past', 'starts_at' => now()->subMonth()]);

        $ordered = app(PublicWebsiteContent::class)->events($this->church->id);

        $this->assertSame(['Past', 'Sooner', 'Later'], $ordered->pluck('title')->all());
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

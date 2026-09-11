<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Filament\Clusters\Website\Resources\WebsiteMessageResource\Pages\ListWebsiteMessages;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteMessage;
use App\PublicWebsite\PublicWebsiteContent;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-C §25-31/§79/§105 — Messages: a lightweight, external-
 * media-only catalogue. `media_url` must always be an external
 * destination (YouTube/Vimeo/podcast) — Keryon hosts no video/audio and
 * renders no raw embed HTML anywhere.
 */
class WebsiteMessageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $this->church = Church::create(['name' => 'Message Test Church', 'slug' => 'message-test-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
    }

    private function image(): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put($path, $bytes);
        $asset = new MediaAsset(['disk' => 'public', 'path' => $path, 'original_filename' => 'message.png', 'mime_type' => 'image/png', 'size' => strlen($bytes), 'width' => 1, 'height' => 1]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    // ---------------------------------------------------------------
    // CRUD / tenancy / authorization
    // ---------------------------------------------------------------

    public function test_a_message_can_be_created_edited_and_deleted(): void
    {
        $message = WebsiteMessage::create(['title' => 'Grace Abounds', 'speaker' => 'Pastor Sam', 'message_date' => now()->toDateString()]);
        $this->assertDatabaseHas('website_messages', ['id' => $message->id, 'church_id' => $this->church->id]);

        $message->update(['title' => 'Grace Abounds More']);
        $this->assertSame('Grace Abounds More', $message->fresh()->title);

        $message->delete();
        $this->assertSoftDeleted('website_messages', ['id' => $message->id]);
    }

    public function test_cross_church_isolation(): void
    {
        WebsiteMessage::create(['title' => 'Church A Message']);

        $otherChurch = Church::create(['name' => 'Other Church', 'slug' => 'message-other-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->assertSame(0, WebsiteMessage::query()->count());
    }

    public function test_communications_can_manage_messages_care_cannot(): void
    {
        $this->assertTrue(Gate::allows('viewAny', WebsiteMessage::class));

        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(Gate::allows('viewAny', WebsiteMessage::class));
        $this->assertFalse(Gate::allows('create', WebsiteMessage::class));
    }

    // ---------------------------------------------------------------
    // Validation / external-media-only contract (§28/§79)
    // ---------------------------------------------------------------

    public function test_title_is_required_via_the_management_form(): void
    {
        Livewire::test(ListWebsiteMessages::class)
            ->callAction('create', data: [])
            ->assertHasActionErrors(['title' => 'required']);
    }

    public function test_unsafe_media_url_protocol_is_rejected_by_form_validation(): void
    {
        Livewire::test(ListWebsiteMessages::class)
            ->callAction('create', data: [
                'title' => 'Unsafe Media',
                'media_url' => 'javascript:alert(1)',
            ])
            ->assertHasActionErrors(['media_url']);
    }

    public function test_media_url_field_accepts_no_raw_embed_html_field_exists(): void
    {
        // K-WEB-V1-001D-C §28/§105 — a structural proof that no raw
        // HTML/embed-code field exists anywhere on the model or form —
        // only a plain external URL.
        $this->assertSame(
            ['title', 'speaker', 'message_date', 'scripture_reference', 'summary', 'image_id', 'image_alt_override', 'media_url', 'is_featured'],
            (new WebsiteMessage)->getFillable(),
        );
    }

    public function test_a_cross_church_media_asset_cannot_be_assigned_as_the_message_image(): void
    {
        $otherChurch = Church::create(['name' => 'Foreign Church', 'slug' => 'message-foreign-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        $foreignImage = $this->image();

        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->expectException(\LogicException::class);
        WebsiteMessage::create(['title' => 'x', 'image_id' => $foreignImage->id]);
    }

    // ---------------------------------------------------------------
    // Sorting (§26/§83) — date-based, no manual sort_order column
    // ---------------------------------------------------------------

    public function test_messages_are_captured_most_recent_first_by_message_date(): void
    {
        WebsiteMessage::create(['title' => 'Oldest', 'message_date' => now()->subMonth()->toDateString()]);
        WebsiteMessage::create(['title' => 'Newest', 'message_date' => now()->toDateString()]);
        WebsiteMessage::create(['title' => 'Middle', 'message_date' => now()->subWeek()->toDateString()]);

        $ordered = app(PublicWebsiteContent::class)->messages($this->church->id);

        $this->assertSame(['Newest', 'Middle', 'Oldest'], $ordered->pluck('title')->all());
    }

    public function test_no_sort_order_column_exists_on_the_messages_table(): void
    {
        $this->assertFalse(Schema::hasColumn('website_messages', 'sort_order'));
    }

    public function test_a_message_with_no_date_sorts_after_every_dated_message(): void
    {
        // K-PROCLAIM-V1-001C §9 — a message added without a date (an
        // editorial choice the form explicitly allows, since
        // `message_date` isn't required) must not be treated as "most
        // recent" by a NULLS-FIRST default; it should read as the
        // least-recent item instead. Verified live against fixture
        // data before being formalized here.
        WebsiteMessage::create(['title' => 'Dated Message', 'message_date' => now()->subMonth()]);
        WebsiteMessage::create(['title' => 'Undated Message']);

        $ordered = app(PublicWebsiteContent::class)->messages($this->church->id);

        $this->assertSame(['Dated Message', 'Undated Message'], $ordered->pluck('title')->all());
    }
}

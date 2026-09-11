<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\PublicationType;
use App\Enums\WebsitePageType;
use App\Filament\Clusters\Website\Resources\ChurchPublicationResource\Pages\ListChurchPublications;
use App\Models\Church;
use App\Models\ChurchPublication;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePageSetting;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\PublicWebsiteContent;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-C §32-41/§80/§106 — Publications. The Product-Office-
 * locked model name `ChurchPublication` (never `Publication`, never
 * `WebsitePublication` — that name already belongs to the immutable
 * Website-deployment-evidence model) is asserted structurally here.
 * `price_text` is bounded display metadata only; `purchase_url` is an
 * optional external destination only — no native commerce exists.
 */
class ChurchPublicationTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $this->church = Church::create(['name' => 'Publication Test Church', 'slug' => 'publication-test-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
    }

    private function image(): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put($path, $bytes);
        $asset = new MediaAsset(['disk' => 'public', 'path' => $path, 'original_filename' => 'cover.png', 'mime_type' => 'image/png', 'size' => strlen($bytes), 'width' => 1, 'height' => 1]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    // ---------------------------------------------------------------
    // Locked naming (§35/§109/§150)
    // ---------------------------------------------------------------

    public function test_the_model_class_is_named_church_publication_not_publication_or_website_publication(): void
    {
        $this->assertTrue(class_exists(ChurchPublication::class));
        $this->assertFalse(class_exists('App\\Models\\Publication'));

        // WebsitePublication remains the pre-existing, unrelated,
        // immutable Website-deployment-evidence model — never repurposed.
        $this->assertNotSame(ChurchPublication::class, WebsitePublication::class);
    }

    public function test_the_table_is_named_church_publications(): void
    {
        $this->assertSame('church_publications', (new ChurchPublication)->getTable());
    }

    // ---------------------------------------------------------------
    // CRUD / tenancy / authorization
    // ---------------------------------------------------------------

    public function test_a_publication_can_be_created_edited_and_deleted(): void
    {
        $publication = ChurchPublication::create(['title' => 'Walking in Faith', 'author' => 'Pastor Sam']);
        $this->assertDatabaseHas('church_publications', ['id' => $publication->id, 'church_id' => $this->church->id]);

        $publication->update(['title' => 'Walking in Faith, Revised']);
        $this->assertSame('Walking in Faith, Revised', $publication->fresh()->title);

        $publication->delete();
        $this->assertSoftDeleted('church_publications', ['id' => $publication->id]);
    }

    public function test_cross_church_isolation(): void
    {
        ChurchPublication::create(['title' => 'Church A Book']);

        $otherChurch = Church::create(['name' => 'Other Church', 'slug' => 'publication-other-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->assertSame(0, ChurchPublication::query()->count());
    }

    public function test_communications_can_manage_publications_care_cannot(): void
    {
        $this->assertTrue(Gate::allows('viewAny', ChurchPublication::class));

        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(Gate::allows('viewAny', ChurchPublication::class));
        $this->assertFalse(Gate::allows('create', ChurchPublication::class));
    }

    // ---------------------------------------------------------------
    // Validation / bounded-metadata contract (§37/§40/§80)
    // ---------------------------------------------------------------

    public function test_title_is_required_via_the_management_form(): void
    {
        Livewire::test(ListChurchPublications::class)
            ->callAction('create', data: ['publication_type' => PublicationType::Book->value])
            ->assertHasActionErrors(['title' => 'required']);
    }

    public function test_publication_type_defaults_to_book_and_is_a_bounded_enum(): void
    {
        $publication = ChurchPublication::create(['title' => 'Untyped']);

        $this->assertSame(PublicationType::Book, $publication->fresh()->publication_type);
        $this->assertCount(6, PublicationType::cases());
    }

    public function test_unsafe_purchase_url_protocol_is_rejected_by_form_validation(): void
    {
        Livewire::test(ListChurchPublications::class)
            ->callAction('create', data: [
                'title' => 'Unsafe Purchase',
                'publication_type' => PublicationType::Book->value,
                'purchase_url' => 'javascript:alert(1)',
            ])
            ->assertHasActionErrors(['purchase_url']);
    }

    public function test_price_text_is_bounded_plain_display_text_never_transactional(): void
    {
        // Structural proof (§37/§150): no price/currency/amount numeric
        // column, no cart, no order table exists — price_text is the
        // only price-related field, and it is a bounded string.
        $publication = ChurchPublication::create(['title' => 'Priced', 'price_text' => '₦8,000']);
        $this->assertSame('₦8,000', $publication->fresh()->price_text);
        $this->assertFalse(Schema::hasTable('church_publication_orders'));
        $this->assertFalse(Schema::hasTable('carts'));
    }

    public function test_a_cross_church_media_asset_cannot_be_assigned_as_the_publication_cover(): void
    {
        $otherChurch = Church::create(['name' => 'Foreign Church', 'slug' => 'publication-foreign-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        $foreignCover = $this->image();

        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->expectException(\LogicException::class);
        ChurchPublication::create(['title' => 'x', 'cover_id' => $foreignCover->id]);
    }

    // ---------------------------------------------------------------
    // Sorting (§34/§83)
    // ---------------------------------------------------------------

    public function test_publications_are_captured_in_sort_order(): void
    {
        ChurchPublication::create(['title' => 'Second', 'sort_order' => 2]);
        ChurchPublication::create(['title' => 'First', 'sort_order' => 1]);

        $ordered = app(PublicWebsiteContent::class)->publications($this->church->id);

        $this->assertSame(['First', 'Second'], $ordered->pluck('title')->all());
    }

    // ---------------------------------------------------------------
    // K-PROCLAIM-V1-001D-R — deterministic tie-break. `sort_order`
    // defaults to `0` for every new Publication; a Church that never
    // drags the Filament reorder handle has every record tied.
    // `ORDER BY sort_order` alone has no contractual secondary order —
    // only `id` does. These tests would fail if that secondary
    // `orderBy('id')` were ever removed.
    // ---------------------------------------------------------------

    public function test_publications_with_a_tied_sort_order_fall_back_to_ascending_id_in_working_content(): void
    {
        $a = ChurchPublication::create(['title' => 'Tied Alpha', 'sort_order' => 0]);
        $b = ChurchPublication::create(['title' => 'Tied Beta', 'sort_order' => 0]);
        $c = ChurchPublication::create(['title' => 'Tied Gamma', 'sort_order' => 0]);
        $this->assertTrue($a->id < $b->id && $b->id < $c->id, 'Fixture must produce a known ascending-ID sequence.');

        $ordered = app(PublicWebsiteContent::class)->publications($this->church->id);

        $this->assertSame(['Tied Alpha', 'Tied Beta', 'Tied Gamma'], $ordered->pluck('title')->all());
    }

    public function test_publications_with_a_tied_sort_order_are_captured_in_ascending_id_order_in_the_published_snapshot(): void
    {
        ChurchPublication::create(['title' => 'Tied Alpha', 'sort_order' => 0]);
        ChurchPublication::create(['title' => 'Tied Beta', 'sort_order' => 0]);
        ChurchPublication::create(['title' => 'Tied Gamma', 'sort_order' => 0]);
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome']);

        $publication = app(WebsitePublisher::class)->publish();

        $this->assertSame(['Tied Alpha', 'Tied Beta', 'Tied Gamma'], array_column($publication->snapshot['publications'], 'title'));
    }

    public function test_publications_with_a_tied_sort_order_render_in_ascending_id_order_on_the_anonymous_public_page(): void
    {
        ChurchPublication::create(['title' => 'Tied Alpha', 'sort_order' => 0]);
        ChurchPublication::create(['title' => 'Tied Beta', 'sort_order' => 0]);
        ChurchPublication::create(['title' => 'Tied Gamma', 'sort_order' => 0]);

        $response = $this->publicGet('/publications');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertTrue(
            strpos($body, 'Tied Alpha') < strpos($body, 'Tied Beta') && strpos($body, 'Tied Beta') < strpos($body, 'Tied Gamma'),
            'Tied publications must render in ascending-id order on the real anonymous public page.'
        );
    }

    // ---------------------------------------------------------------
    // K-PROCLAIM-V1-001D — dedicated public page rendering, empty
    // state, long content, and cross-Church isolation. Same anonymity
    // contract as {@see ProclaimRenderingTest::publicGet()}.
    // ---------------------------------------------------------------

    /** Same anonymity contract as {@see ProclaimRenderingTest::publicGet()}. */
    private function publicGet(string $path): TestResponse
    {
        WebsiteSettings::firstOrCreate(['church_id' => $this->church->id], ['theme' => 'proclaim']);
        WebsiteHomeContent::firstOrCreate(['church_id' => $this->church->id], ['hero_heading' => 'Welcome']);
        WebsitePageSetting::updateOrCreate(
            ['church_id' => $this->church->id, 'page_type' => WebsitePageType::Publications->value],
            ['enabled' => true],
        );
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        return $this->get("http://{$this->church->slug}.keryon.app{$path}");
    }

    public function test_a_very_long_title_and_author_render_safely_on_the_public_page(): void
    {
        $longTitle = 'The Long Walk Home: Stories of Grace, Endurance, and Everyday Faith from Members of Our Congregation';
        $longAuthor = 'Compiled by the Publication Test Church Writers Circle and Congregational Storytelling Ministry';
        ChurchPublication::create(['title' => $longTitle, 'author' => $longAuthor]);

        $this->publicGet('/publications')->assertOk()->assertSee($longTitle)->assertSee($longAuthor);
    }

    public function test_the_public_page_shows_a_visitor_appropriate_empty_state_with_zero_publications(): void
    {
        $response = $this->publicGet('/publications');

        $response->assertOk()
            ->assertSee('No publications are available yet.')
            ->assertSee('Please check back for updates from Publication Test Church.')
            ->assertDontSee('No records')
            ->assertDontSee('Nothing configured')
            ->assertDontSee('0 items');
    }

    public function test_another_churchs_publications_never_appear_on_this_churchs_public_page(): void
    {
        $this->publicGet('/publications');

        $other = Church::create(['name' => 'Other Publication Church', 'slug' => 'publication-other-church-public']);
        app(TenantContext::class)->forgetResolved();
        $this->actingAs(User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome']);
        WebsitePageSetting::updateOrCreate(['page_type' => WebsitePageType::Publications->value], ['enabled' => true]);
        ChurchPublication::create(['title' => "Other Church's Secret Publication"]);
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->get('http://publication-test-church.keryon.app/publications')
            ->assertOk()->assertDontSee("Other Church's Secret Publication");
    }
}

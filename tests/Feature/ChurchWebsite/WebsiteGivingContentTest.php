<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\WebsitePageType;
use App\Filament\Clusters\Website\Pages\EditGiving;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteGivingContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePageSetting;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-C §42-47/§81/§107 — Giving is a singleton Church page
 * following the `WebsiteHomeContent`/`WebsiteContactContent` pattern.
 * No payment processing, no country-specific banking field, no
 * transactional table exists anywhere in this model or test.
 */
class WebsiteGivingContentTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $this->church = Church::create(['name' => 'Giving Test Church', 'slug' => 'giving-test-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
    }

    private function image(): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put($path, $bytes);
        $asset = new MediaAsset(['disk' => 'public', 'path' => $path, 'original_filename' => 'giving.png', 'mime_type' => 'image/png', 'size' => strlen($bytes), 'width' => 1, 'height' => 1]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    // ---------------------------------------------------------------
    // Singleton persistence / tenancy / authorization
    // ---------------------------------------------------------------

    public function test_giving_page_persists_headline_and_body_via_the_management_page(): void
    {
        Livewire::test(EditGiving::class)
            ->fillForm([
                'headline' => 'Give Generously',
                'body' => 'Your generosity fuels our mission.',
                'cta_label' => 'Give Now',
                'giving_url' => 'https://giving.example-church.org',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $giving = WebsiteGivingContent::first();
        $this->assertSame('Give Generously', $giving->headline);
        $this->assertSame('Give Now', $giving->cta_label);
    }

    public function test_only_one_giving_content_row_per_church_is_permitted(): void
    {
        WebsiteGivingContent::create(['headline' => 'First']);

        Livewire::test(EditGiving::class)
            ->fillForm(['headline' => 'Updated Via Page'])
            ->call('save');

        $this->assertSame(1, WebsiteGivingContent::query()->count());
        $this->assertSame('Updated Via Page', WebsiteGivingContent::first()->headline);
    }

    public function test_giving_content_saved_by_church_a_is_not_visible_to_church_b(): void
    {
        WebsiteGivingContent::create(['headline' => "Church A's Giving"]);

        $churchB = Church::create(['name' => 'Church B', 'slug' => 'giving-church-b']);
        $this->actingAs(User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        Livewire::test(EditGiving::class)->assertFormSet(['headline' => null]);
        $this->assertSame(0, WebsiteGivingContent::query()->count());
        $this->assertSame(
            1,
            WebsiteGivingContent::withoutGlobalScope('church_tenant')->count(),
        );
    }

    public function test_communications_can_manage_giving_care_cannot(): void
    {
        $this->assertTrue(Gate::allows('viewAny', WebsiteGivingContent::class));
        $this->assertTrue(EditGiving::canAccess());

        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(Gate::allows('viewAny', WebsiteGivingContent::class));
        $this->assertFalse(EditGiving::canAccess());
    }

    // ---------------------------------------------------------------
    // No transactional/payment field exists (§46/§150)
    // ---------------------------------------------------------------

    public function test_no_payment_or_banking_field_exists_on_the_giving_content_model(): void
    {
        $this->assertSame(
            ['headline', 'body', 'image_id', 'image_alt_override', 'cta_label', 'giving_url', 'additional_instructions'],
            (new WebsiteGivingContent)->getFillable(),
        );
    }

    public function test_unsafe_giving_url_protocol_is_rejected_by_form_validation(): void
    {
        Livewire::test(EditGiving::class)
            ->fillForm(['giving_url' => 'javascript:alert(1)'])
            ->call('save')
            ->assertHasFormErrors(['giving_url']);
    }

    public function test_a_cross_church_media_asset_cannot_be_assigned_as_the_giving_image(): void
    {
        $otherChurch = Church::create(['name' => 'Foreign Church', 'slug' => 'giving-foreign-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        $foreignImage = $this->image();

        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->expectException(\LogicException::class);
        WebsiteGivingContent::create(['headline' => 'x', 'image_id' => $foreignImage->id]);
    }

    // ---------------------------------------------------------------
    // K-PROCLAIM-V1-001D — dedicated public page rendering: populated,
    // empty/absent, additional-instructions grouping, CTA safety, and
    // cross-Church isolation. Same anonymity contract as
    // {@see ProclaimRenderingTest::publicGet()}.
    // ---------------------------------------------------------------

    /** Same anonymity contract as {@see ProclaimRenderingTest::publicGet()}. */
    private function publicGet(string $path = '/giving'): TestResponse
    {
        WebsiteSettings::firstOrCreate(['church_id' => $this->church->id], ['theme' => 'proclaim']);
        WebsiteHomeContent::firstOrCreate(['church_id' => $this->church->id], ['hero_heading' => 'Welcome']);
        WebsitePageSetting::updateOrCreate(
            ['church_id' => $this->church->id, 'page_type' => WebsitePageType::Giving->value],
            ['enabled' => true],
        );
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        return $this->get("http://{$this->church->slug}.keryon.app{$path}");
    }

    public function test_populated_giving_content_renders_headline_body_and_cta_on_the_public_page(): void
    {
        WebsiteGivingContent::create([
            'headline' => 'Give Generously',
            'body' => 'Your generosity fuels our mission.',
            'cta_label' => 'Give Now',
            'giving_url' => 'https://giving.example.org',
        ]);

        $this->publicGet()->assertOk()
            ->assertSee('Give Generously')
            ->assertSee('Your generosity fuels our mission.')
            ->assertSee('href="https://giving.example.org"', false)
            ->assertSee('Give Now');
    }

    public function test_additional_instructions_render_grouped_beneath_a_more_ways_to_give_label(): void
    {
        WebsiteGivingContent::create([
            'headline' => 'Give Generously',
            'body' => 'Your generosity fuels our mission.',
            'additional_instructions' => 'You can also give by texting HOPE to 55555.',
        ]);

        $this->publicGet()->assertOk()
            ->assertSee('More ways to give')
            ->assertSee('You can also give by texting HOPE to 55555.');
    }

    public function test_giving_page_shows_a_visitor_appropriate_empty_state_when_no_content_row_exists(): void
    {
        $response = $this->publicGet();

        $response->assertOk()
            ->assertSee('Giving information is coming soon.')
            ->assertSee('Please check back for updates from Giving Test Church.')
            ->assertDontSee('No records')
            ->assertDontSee('Nothing configured');
    }

    public function test_giving_page_shows_the_empty_state_when_a_content_row_exists_but_headline_and_body_are_both_blank(): void
    {
        WebsiteGivingContent::create(['giving_url' => 'https://giving.example.org']);

        $response = $this->publicGet();

        $response->assertOk()
            ->assertSee('Giving information is coming soon.')
            ->assertDontSee('Give Now');
    }

    public function test_the_cta_never_renders_when_giving_url_is_absent_even_with_a_cta_label_set(): void
    {
        WebsiteGivingContent::create(['headline' => 'Give Generously', 'body' => 'Body copy.', 'cta_label' => 'Give Now']);

        $this->publicGet()->assertOk()->assertDontSee('Give Now');
    }

    public function test_another_churchs_giving_content_never_appears_on_this_churchs_public_page(): void
    {
        $this->publicGet();

        $other = Church::create(['name' => 'Other Giving Church', 'slug' => 'giving-other-church-public']);
        app(TenantContext::class)->forgetResolved();
        $this->actingAs(User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome']);
        WebsitePageSetting::updateOrCreate(['page_type' => WebsitePageType::Giving->value], ['enabled' => true]);
        WebsiteGivingContent::create(['headline' => "Other Church's Secret Giving Pitch"]);
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->get('http://giving-test-church.keryon.app/giving')
            ->assertOk()->assertDontSee("Other Church's Secret Giving Pitch");
    }
}

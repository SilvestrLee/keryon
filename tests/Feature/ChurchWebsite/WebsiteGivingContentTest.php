<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Filament\Clusters\Website\Pages\EditGiving;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteGivingContent;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
}

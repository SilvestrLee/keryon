<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\LeadershipCategory;
use App\Enums\WebsiteTheme;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteContactContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMinistry;
use App\Models\WebsiteSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * K-CHURCHWEB-001B §37 — domain-boundary proof for the canonical Church
 * Website Content domain: tenant ownership, cross-Church isolation,
 * capability enforcement, repeatable structures staying tenant-safe,
 * media references never crossing a Church boundary, and theme selection
 * never mutating content.
 */
class WebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function commsUserFor(Church $church): User
    {
        return User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
    }

    public function test_website_settings_defaults_to_proclaim_and_is_tenant_scoped(): void
    {
        $church = Church::create(['name' => 'Settings Church', 'slug' => 'settings-church']);
        $this->actingAs($this->commsUserFor($church));

        $settings = WebsiteSettings::create([]);

        $this->assertSame($church->id, $settings->church_id);
        $this->assertSame(WebsiteTheme::PROCLAIM, $settings->fresh()->theme);
    }

    public function test_changing_theme_selection_does_not_mutate_website_content(): void
    {
        $church = Church::create(['name' => 'Theme Church', 'slug' => 'theme-church']);
        $this->actingAs($this->commsUserFor($church));

        $settings = WebsiteSettings::create([]);
        $home = WebsiteHomeContent::create(['hero_heading' => 'Welcome Home']);

        // Only ever a persisted enum on WebsiteSettings — changing it
        // touches no other table (Theme != Content, K-CHURCHWEB-001B §14).
        $settings->update(['theme' => WebsiteTheme::PROCLAIM->value]);

        $this->assertSame('Welcome Home', $home->fresh()->hero_heading);
    }

    public function test_home_content_is_tenant_scoped_and_church_a_cannot_view_church_bs(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-home']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-home']);

        $this->actingAs($this->commsUserFor($churchB));
        $homeB = WebsiteHomeContent::create(['hero_heading' => 'Church B Hero']);

        $this->actingAs($this->commsUserFor($churchA));

        $this->assertFalse(Gate::allows('view', $homeB));
        $this->assertNull(WebsiteHomeContent::find($homeB->id));
    }

    public function test_home_content_hero_image_reference_cannot_cross_church_boundary(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-home-media']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-home-media']);

        $this->actingAs($this->commsUserFor($churchA));
        $assetA = MediaAsset::create([
            'disk' => 'public',
            'path' => 'tenants/1/media/uuid/hero.jpg',
            'original_filename' => 'hero.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 512000,
        ]);

        $this->actingAs($this->commsUserFor($churchB));

        $this->expectException(InvalidArgumentException::class);

        WebsiteHomeContent::create(['hero_image_id' => $assetA->id]);
    }

    public function test_hero_image_alt_text_falls_back_to_asset_default_then_prefers_override(): void
    {
        $church = Church::create(['name' => 'Alt Church', 'slug' => 'alt-church']);
        $this->actingAs($this->commsUserFor($church));

        $asset = MediaAsset::create([
            'disk' => 'public',
            'path' => 'tenants/1/media/uuid/hero.jpg',
            'original_filename' => 'hero.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 512000,
            'alt_text' => 'Default sanctuary photo',
        ]);

        $home = WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        $this->assertSame('Default sanctuary photo', $home->heroImageAltText());

        $home->update(['hero_image_alt_override' => 'Congregation gathered for Easter service']);
        $this->assertSame('Congregation gathered for Easter service', $home->fresh()->heroImageAltText());
    }

    public function test_about_content_is_tenant_scoped(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-about']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-about']);

        $this->actingAs($this->commsUserFor($churchB));
        $aboutB = WebsiteAboutContent::create(['church_story' => "Church B's story."]);

        $this->actingAs($this->commsUserFor($churchA));

        $this->assertFalse(Gate::allows('view', $aboutB));
        $this->assertNull(WebsiteAboutContent::find($aboutB->id));
    }

    public function test_contact_content_is_tenant_scoped(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-contact']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-contact']);

        $this->actingAs($this->commsUserFor($churchB));
        $contactB = WebsiteContactContent::create(['office_hours' => 'Mon-Fri 9am-5pm']);

        $this->actingAs($this->commsUserFor($churchA));

        $this->assertFalse(Gate::allows('view', $contactB));
        $this->assertNull(WebsiteContactContent::find($contactB->id));
    }

    public function test_contact_content_does_not_duplicate_church_address(): void
    {
        // Domain-level confirmation of the §22 ownership test: address
        // lives on Church, not duplicated as a WebsiteContactContent
        // column.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('website_contact_contents', 'address'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('churches', 'address'));
    }

    public function test_leadership_profiles_are_repeatable_ordered_and_tenant_scoped(): void
    {
        $church = Church::create(['name' => 'Leadership Church', 'slug' => 'leadership-church']);
        $this->actingAs($this->commsUserFor($church));

        WebsiteLeadershipProfile::create([
            'name' => 'Pastor Jane',
            'category' => LeadershipCategory::PASTOR->value,
            'role_title' => 'Senior Pastor',
            'sort_order' => 1,
        ]);
        WebsiteLeadershipProfile::create([
            'name' => 'Elder John',
            'category' => LeadershipCategory::ELDER->value,
            'sort_order' => 2,
        ]);

        $profiles = WebsiteLeadershipProfile::orderBy('sort_order')->get();

        $this->assertCount(2, $profiles);
        $this->assertSame('Pastor Jane', $profiles->first()->name);
        $this->assertSame(LeadershipCategory::PASTOR, $profiles->first()->category);
    }

    public function test_church_a_cannot_view_church_bs_leadership_profiles(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-leadership']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-leadership']);

        $this->actingAs($this->commsUserFor($churchB));
        $profileB = WebsiteLeadershipProfile::create([
            'name' => 'Pastor B',
            'category' => LeadershipCategory::PASTOR->value,
        ]);

        $this->actingAs($this->commsUserFor($churchA));

        $this->assertFalse(Gate::allows('view', $profileB));
        $this->assertNull(WebsiteLeadershipProfile::find($profileB->id));
        $this->assertCount(0, WebsiteLeadershipProfile::all());
    }

    public function test_leadership_profile_photo_reference_cannot_cross_church_boundary(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-leadership-media']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-leadership-media']);

        $this->actingAs($this->commsUserFor($churchA));
        $assetA = MediaAsset::create([
            'disk' => 'public',
            'path' => 'tenants/1/media/uuid/pastor.jpg',
            'original_filename' => 'pastor.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 204800,
        ]);

        $this->actingAs($this->commsUserFor($churchB));

        $this->expectException(InvalidArgumentException::class);

        WebsiteLeadershipProfile::create([
            'name' => 'Pastor B',
            'category' => LeadershipCategory::PASTOR->value,
            'photo_id' => $assetA->id,
        ]);
    }

    public function test_leadership_profile_soft_deletes(): void
    {
        $church = Church::create(['name' => 'Soft Delete Leadership Church', 'slug' => 'soft-delete-leadership-church']);
        $this->actingAs($this->commsUserFor($church));

        $profile = WebsiteLeadershipProfile::create([
            'name' => 'Pastor Jane',
            'category' => LeadershipCategory::PASTOR->value,
        ]);
        $profile->delete();

        $this->assertSoftDeleted($profile);
    }

    public function test_ministries_are_repeatable_ordered_and_tenant_scoped(): void
    {
        $church = Church::create(['name' => 'Ministry Church', 'slug' => 'ministry-church']);
        $this->actingAs($this->commsUserFor($church));

        WebsiteMinistry::create(['name' => 'Youth Ministry', 'sort_order' => 1]);
        WebsiteMinistry::create(['name' => "Children's Ministry", 'sort_order' => 2]);

        $ministries = WebsiteMinistry::orderBy('sort_order')->get();

        $this->assertCount(2, $ministries);
        $this->assertSame('Youth Ministry', $ministries->first()->name);
    }

    public function test_church_a_cannot_view_church_bs_ministries(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-ministries']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-ministries']);

        $this->actingAs($this->commsUserFor($churchB));
        $ministryB = WebsiteMinistry::create(['name' => "Church B's Ministry"]);

        $this->actingAs($this->commsUserFor($churchA));

        $this->assertFalse(Gate::allows('view', $ministryB));
        $this->assertNull(WebsiteMinistry::find($ministryB->id));
    }

    public function test_ministry_image_reference_cannot_cross_church_boundary(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-ministry-media']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-ministry-media']);

        $this->actingAs($this->commsUserFor($churchA));
        $assetA = MediaAsset::create([
            'disk' => 'public',
            'path' => 'tenants/1/media/uuid/ministry.jpg',
            'original_filename' => 'ministry.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 204800,
        ]);

        $this->actingAs($this->commsUserFor($churchB));

        $this->expectException(InvalidArgumentException::class);

        WebsiteMinistry::create(['name' => 'Cross-Church Ministry', 'image_id' => $assetA->id]);
    }

    public function test_care_user_cannot_view_or_manage_any_website_content(): void
    {
        $church = Church::create(['name' => 'Care Website Church', 'slug' => 'care-website-church']);
        $this->actingAs($this->commsUserFor($church));
        $home = WebsiteHomeContent::create(['hero_heading' => 'Hero']);
        $ministry = WebsiteMinistry::create(['name' => 'Ministry']);

        $careUser = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($careUser);

        $this->assertFalse(Gate::allows('view', $home));
        $this->assertFalse(Gate::allows('view', $ministry));
        $this->assertFalse(Gate::allows('viewAny', WebsiteMinistry::class));
        $this->assertFalse(Gate::allows('viewAny', WebsiteLeadershipProfile::class));
    }

    public function test_administrator_does_not_automatically_gain_website_content_access(): void
    {
        $church = Church::create(['name' => 'Admin Website Church', 'slug' => 'admin-website-church']);
        $this->actingAs($this->commsUserFor($church));
        $home = WebsiteHomeContent::create(['hero_heading' => 'Hero']);

        $adminUser = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($adminUser);

        $this->assertFalse(Gate::allows('view', $home));
        $this->assertFalse(Gate::allows('update', $home));
    }

    public function test_only_one_home_content_row_per_church_is_permitted(): void
    {
        $church = Church::create(['name' => 'Single Home Church', 'slug' => 'single-home-church']);
        $this->actingAs($this->commsUserFor($church));

        WebsiteHomeContent::create(['hero_heading' => 'First']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        WebsiteHomeContent::create(['hero_heading' => 'Second']);
    }
}

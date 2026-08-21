<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\BrandFontChoice;
use App\Enums\Capability;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchMembership;
use App\Models\MediaAsset;
use App\Models\User;
use App\Enums\ChurchRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * K-CHURCHWEB-001B §37 — domain-boundary proof for the shared Church
 * Brand Profile: tenant ownership, cross-Church denial, no-context
 * failure, capability enforcement, invalid-value rejection, and the
 * canonical one-profile-per-Church invariant.
 */
class ChurchBrandProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_communications_user_can_create_and_be_authorized_for_their_own_brand_profile(): void
    {
        $church = Church::create(['name' => 'Brand Church', 'slug' => 'brand-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $profile = ChurchBrandProfile::create([
            'primary_color' => '#132E35',
        ]);

        $this->assertSame($church->id, $profile->church_id);
        $this->assertTrue(Gate::allows('view', $profile));
        $this->assertTrue(Gate::allows('update', $profile));
    }

    public function test_care_user_cannot_view_or_manage_brand_profile(): void
    {
        $church = Church::create(['name' => 'Care Church', 'slug' => 'care-church']);
        $adminUser = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($adminUser);
        $profile = ChurchBrandProfile::create(['primary_color' => '#132E35']);

        $careUser = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($careUser);

        $this->assertFalse(Gate::allows('view', $profile));
        $this->assertFalse(Gate::allows('update', $profile));
    }

    public function test_administrator_does_not_automatically_gain_brand_profile_access(): void
    {
        $church = Church::create(['name' => 'Admin Church', 'slug' => 'admin-church']);
        $commsUser = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($commsUser);
        $profile = ChurchBrandProfile::create(['primary_color' => '#132E35']);

        $adminUser = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($adminUser);

        $this->assertFalse(Gate::allows('view', $profile));
        $this->assertFalse(Gate::allows('update', $profile));
    }

    public function test_church_a_user_cannot_view_church_bs_brand_profile(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-brand']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-brand']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $profileB = ChurchBrandProfile::create(['primary_color' => '#132E35']);

        $this->actingAs($userA);
        $this->assertFalse(Gate::allows('view', $profileB));
        $this->assertFalse(Gate::allows('update', $profileB));

        // Tenant scoping also makes it invisible via normal queries.
        $this->assertNull(ChurchBrandProfile::find($profileB->id));
    }

    public function test_no_tenant_context_denies_access_entirely(): void
    {
        $church = Church::create(['name' => 'No Context Church', 'slug' => 'no-context-brand']);
        $commsUser = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($commsUser);
        $profile = ChurchBrandProfile::create(['primary_color' => '#132E35']);

        // Simulate no resolvable tenant context: log out entirely.
        $this->app['auth']->forgetGuards();

        $this->assertFalse(Gate::allows('view', $profile));
    }

    public function test_invalid_hex_color_is_rejected(): void
    {
        $church = Church::create(['name' => 'Color Church', 'slug' => 'color-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $this->expectException(InvalidArgumentException::class);

        ChurchBrandProfile::create(['primary_color' => 'not-a-color']);
    }

    public function test_valid_hex_color_is_normalized_to_uppercase(): void
    {
        $church = Church::create(['name' => 'Valid Color Church', 'slug' => 'valid-color-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $profile = ChurchBrandProfile::create(['primary_color' => '#abc123']);

        $this->assertSame('#ABC123', $profile->fresh()->primary_color);
    }

    public function test_null_color_is_left_null(): void
    {
        $church = Church::create(['name' => 'Null Color Church', 'slug' => 'null-color-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $profile = ChurchBrandProfile::create([]);

        $this->assertNull($profile->fresh()->primary_color);
    }

    public function test_typography_preference_is_bounded_to_the_approved_catalogue(): void
    {
        $church = Church::create(['name' => 'Font Church', 'slug' => 'font-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $profile = ChurchBrandProfile::create(['heading_font' => BrandFontChoice::GEIST->value]);

        $this->assertSame(BrandFontChoice::GEIST, $profile->fresh()->heading_font);
    }

    public function test_only_one_brand_profile_per_church_is_permitted(): void
    {
        $church = Church::create(['name' => 'Single Profile Church', 'slug' => 'single-profile-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        ChurchBrandProfile::create(['primary_color' => '#132E35']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ChurchBrandProfile::create(['primary_color' => '#E09F3E']);
    }

    public function test_logo_media_reference_cannot_cross_church_boundary(): void
    {
        $churchA = Church::create(['name' => 'Media Church A', 'slug' => 'media-church-a-brand']);
        $churchB = Church::create(['name' => 'Media Church B', 'slug' => 'media-church-b-brand']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userA);
        $assetA = MediaAsset::create([
            'disk' => 'public',
            'path' => 'tenants/1/media/1/logo.svg',
            'original_filename' => 'logo.svg',
            'mime_type' => 'image/svg+xml',
            'size' => 1024,
        ]);

        $this->actingAs($userB);

        $this->expectException(InvalidArgumentException::class);

        ChurchBrandProfile::create(['primary_logo_media_id' => $assetA->id]);
    }
}

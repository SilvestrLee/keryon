<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Filament\Pages\CareCenterDashboard;
use App\Filament\Resources\PrayerRequestResource\Pages\EditPrayerRequest;
use App\Filament\Resources\PrayerRequestResource\Pages\ListPrayerRequests;
use App\Filament\Resources\PrayerRequestResource\Pages\ViewPrayerRequest;
use App\Models\Church;
use App\Models\PrayerRequest;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proves the Care Center security gap identified in K-AUTH-001A §G/§H is
 * closed: Care access requires the explicit Care responsibility, never
 * inherited from Primary, Administrator, or Communications alone. See
 * Keryon Blueprint v1.4.1 §10 and K-AUTH-001B §24-§27/§46.
 */
class CareCenterAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function memberOf(Church $church, array $roles = [], bool $primary = false): User
    {
        return User::factory()->forChurch($church, $roles, $primary)->create();
    }

    // --- Care role: full access within own church -------------------------

    public function test_care_role_can_access_dashboard(): void
    {
        $church = Church::create(['name' => 'Care Church', 'slug' => 'care-church']);
        $user = $this->memberOf($church, [ChurchRole::CARE]);

        $this->actingAs($user);

        Livewire::test(CareCenterDashboard::class)->assertSuccessful();
    }

    public function test_care_role_can_list_view_create_edit_delete_own_church_prayer_requests(): void
    {
        $church = Church::create(['name' => 'Care Church', 'slug' => 'care-church']);
        $user = $this->memberOf($church, [ChurchRole::CARE]);

        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', PrayerRequest::class));
        $this->assertTrue($user->can('create', PrayerRequest::class));

        $prayerRequest = PrayerRequest::create(['request' => 'Please pray for healing.']);

        $this->assertTrue($user->can('view', $prayerRequest));
        $this->assertTrue($user->can('update', $prayerRequest));
        $this->assertTrue($user->can('delete', $prayerRequest));

        Livewire::test(ListPrayerRequests::class)->assertCanSeeTableRecords([$prayerRequest]);
        Livewire::test(ViewPrayerRequest::class, ['record' => $prayerRequest->getKey()])->assertSuccessful();
        Livewire::test(EditPrayerRequest::class, ['record' => $prayerRequest->getKey()])->assertSuccessful();
    }

    public function test_care_role_cannot_access_another_churchs_care_data(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'care-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'care-church-b']);

        $userB = $this->memberOf($churchB, [ChurchRole::CARE]);
        $this->actingAs($userB);
        $prayerRequestB = PrayerRequest::create(['request' => 'Church B request.']);

        $userA = $this->memberOf($churchA, [ChurchRole::CARE]);
        $this->actingAs($userA);

        $this->assertFalse($userA->can('view', $prayerRequestB));
        $this->assertFalse($userA->can('update', $prayerRequestB));
        $this->assertFalse($userA->can('delete', $prayerRequestB));
    }

    // --- Communications-only: full denial ----------------------------------

    public function test_communications_only_cannot_access_care(): void
    {
        $church = Church::create(['name' => 'Comms Church', 'slug' => 'comms-church']);
        $user = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->assertFalse(CareCenterDashboard::canAccess());
        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('create', PrayerRequest::class));

        // Seeded directly via Eloquent (bypassing the Policy, as any
        // non-Filament code path would) purely to prove that even an
        // existing, tenant-owned record in the acting user's own church is
        // still denied without the Care capability.
        $prayerRequest = PrayerRequest::create(['request' => 'Seed.']);

        $this->assertFalse($user->can('view', $prayerRequest));
        $this->assertFalse($user->can('update', $prayerRequest));
        $this->assertFalse($user->can('delete', $prayerRequest));
    }

    // --- Administrator-only: full denial -----------------------------------

    public function test_administrator_only_cannot_access_care(): void
    {
        $church = Church::create(['name' => 'Admin Church', 'slug' => 'admin-church']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);

        $this->actingAs($user);

        $this->assertFalse(CareCenterDashboard::canAccess());
        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('create', PrayerRequest::class));
    }

    // --- Primary-only: full denial ------------------------------------------

    public function test_primary_only_cannot_access_care(): void
    {
        $church = Church::create(['name' => 'Primary Church', 'slug' => 'primary-church']);
        $user = $this->memberOf($church, [], primary: true);

        $this->actingAs($user);

        $this->assertFalse(CareCenterDashboard::canAccess());
        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('create', PrayerRequest::class));
    }

    // --- Composable roles: allow only when Care is held ---------------------

    public function test_administrator_plus_care_can_access_care(): void
    {
        $church = Church::create(['name' => 'Admin Care Church', 'slug' => 'admin-care-church']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR, ChurchRole::CARE]);

        $this->actingAs($user);

        $this->assertTrue(CareCenterDashboard::canAccess());
        $this->assertTrue($user->can('viewAny', PrayerRequest::class));
    }

    public function test_communications_plus_care_can_access_care(): void
    {
        $church = Church::create(['name' => 'Comms Care Church', 'slug' => 'comms-care-church']);
        $user = $this->memberOf($church, [ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);

        $this->actingAs($user);

        $this->assertTrue(CareCenterDashboard::canAccess());
        $this->assertTrue($user->can('viewAny', PrayerRequest::class));
    }

    // --- Direct-URL proof against registered routes -------------------------

    public function test_communications_only_direct_url_to_care_center_dashboard_is_rejected(): void
    {
        $church = Church::create(['name' => 'Direct URL Church', 'slug' => 'direct-url-church']);
        $user = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->get('/admin/care-center')->assertForbidden();
    }

    public function test_administrator_only_direct_url_to_prayer_requests_list_is_rejected(): void
    {
        $church = Church::create(['name' => 'Direct URL Church', 'slug' => 'direct-url-church-2']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);

        $this->actingAs($user);

        $this->get('/admin/prayer-requests')->assertForbidden();
    }

    public function test_primary_only_direct_url_to_prayer_request_view_is_rejected(): void
    {
        $church = Church::create(['name' => 'Direct URL Church', 'slug' => 'direct-url-church-3']);
        $careUser = $this->memberOf($church, [ChurchRole::CARE]);

        $this->actingAs($careUser);
        $prayerRequest = PrayerRequest::create(['request' => 'Seed for direct URL test.']);

        $primaryOnly = $this->memberOf($church, [], primary: true);
        $this->actingAs($primaryOnly);

        $this->get("/admin/prayer-requests/{$prayerRequest->id}")->assertForbidden();
        $this->get("/admin/prayer-requests/{$prayerRequest->id}/edit")->assertForbidden();
    }

    public function test_guest_is_redirected_not_forbidden(): void
    {
        $this->get('/admin/care-center')->assertRedirect('/admin/login');
        $this->get('/admin/prayer-requests')->assertRedirect('/admin/login');
    }
}

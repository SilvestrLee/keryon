<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Filament\Resources\CongregationResource\Pages\ListCongregationMembers;
use App\Models\Church;
use App\Models\CongregationMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proves the Congregation read/manage split (Keryon Blueprint v1.4.1 §9,
 * K-AUTH-001B §28-§29/§47): Administrator manages, Communications and Care
 * are read-only, and access requires the appropriate capability under a
 * valid TenantContext with tenant ownership preserved.
 */
class CongregationAuthorizationTest extends TestCase
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

    public function test_administrator_can_view_create_edit_delete(): void
    {
        $church = Church::create(['name' => 'Admin Church', 'slug' => 'admin-cong-church']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);

        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', CongregationMember::class));
        $this->assertTrue($user->can('create', CongregationMember::class));

        $member = CongregationMember::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '555-0100', 'status' => 'active']);

        $this->assertTrue($user->can('view', $member));
        $this->assertTrue($user->can('update', $member));
        $this->assertTrue($user->can('delete', $member));
    }

    public function test_communications_is_read_only(): void
    {
        $church = Church::create(['name' => 'Comms Church', 'slug' => 'comms-cong-church']);
        $admin = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($admin);
        $member = CongregationMember::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '555-0100', 'status' => 'active']);

        $user = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', CongregationMember::class));
        $this->assertTrue($user->can('view', $member));

        $this->assertFalse($user->can('create', CongregationMember::class));
        $this->assertFalse($user->can('update', $member));
        $this->assertFalse($user->can('delete', $member));
    }

    public function test_care_is_read_only(): void
    {
        $church = Church::create(['name' => 'Care Church', 'slug' => 'care-cong-church']);
        $admin = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($admin);
        $member = CongregationMember::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '555-0100', 'status' => 'active']);

        $user = $this->memberOf($church, [ChurchRole::CARE]);
        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', CongregationMember::class));
        $this->assertTrue($user->can('view', $member));

        $this->assertFalse($user->can('create', CongregationMember::class));
        $this->assertFalse($user->can('update', $member));
        $this->assertFalse($user->can('delete', $member));
    }

    public function test_primary_only_cannot_access_congregation(): void
    {
        $church = Church::create(['name' => 'Primary Church', 'slug' => 'primary-cong-church']);
        $user = $this->memberOf($church, [], primary: true);

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', CongregationMember::class));
        $this->assertFalse($user->can('create', CongregationMember::class));
    }

    public function test_read_only_filament_experience_hides_mutation_actions(): void
    {
        $church = Church::create(['name' => 'Filament Church', 'slug' => 'filament-cong-church']);
        $admin = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($admin);
        $member = CongregationMember::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '555-0100', 'status' => 'active']);

        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $this->actingAs($comms);

        Livewire::test(ListCongregationMembers::class)
            ->assertCanSeeTableRecords([$member])
            ->assertTableActionHidden('edit', $member)
            ->assertTableActionHidden('delete', $member)
            ->assertActionHidden('create');
    }

    public function test_administrator_sees_mutation_actions(): void
    {
        $church = Church::create(['name' => 'Filament Admin Church', 'slug' => 'filament-admin-cong-church']);
        $admin = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($admin);
        $member = CongregationMember::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '555-0100', 'status' => 'active']);

        Livewire::test(ListCongregationMembers::class)
            ->assertCanSeeTableRecords([$member])
            ->assertTableActionVisible('edit', $member)
            ->assertTableActionVisible('delete', $member)
            ->assertActionVisible('create');
    }

    public function test_cross_church_congregation_access_is_denied(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'cong-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'cong-church-b']);

        $adminB = $this->memberOf($churchB, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($adminB);
        $memberB = CongregationMember::create(['first_name' => 'Bob', 'last_name' => 'B', 'phone' => '555-0002', 'status' => 'active']);

        $adminA = $this->memberOf($churchA, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($adminA);

        $this->assertFalse($adminA->can('view', $memberB));
        $this->assertFalse($adminA->can('update', $memberB));
        $this->assertFalse($adminA->can('delete', $memberB));
    }

    public function test_no_tenant_context_denies_congregation_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', CongregationMember::class));
    }
}

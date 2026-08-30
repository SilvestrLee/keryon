<?php

namespace Tests\Feature\Dashboard;

use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\CongregationMember;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class DashboardTenancyTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    public function test_selected_church_rebuilds_snapshot_without_sibling_data(): void
    {
        [$churchA, $user] = $this->dashboardActor([ChurchRole::ADMINISTRATOR], churchAttributes: ['name' => 'Church A']);
        CongregationMember::query()->insert([
            ['church_id' => $churchA->id, 'first_name' => 'One', 'last_name' => 'Member', 'phone' => '1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['church_id' => $churchA->id, 'first_name' => 'Two', 'last_name' => 'Member', 'phone' => '2', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $churchB = Church::factory()->create(['name' => 'Church B']);
        $membershipB = ChurchMembership::factory()->for($churchB)->for($user)->create();
        $membershipB->assignRoles([ChurchRole::ADMINISTRATOR]);

        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();
        $first = app(ChurchDashboardSnapshotBuilder::class)->build();

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();
        $second = app(ChurchDashboardSnapshotBuilder::class)->build();

        $this->assertSame('Church A', $first->churchName);
        $this->assertSame(2, $first->summaries['congregation'][0]->value);
        $this->assertSame('Church B', $second->churchName);
        $this->assertSame(0, $second->summaries['congregation'][0]->value);
    }

    public function test_missing_selection_and_suspended_membership_fail_closed(): void
    {
        [, $user, $membership] = $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $other = Church::factory()->create();
        ChurchMembership::factory()->for($other)->for($user)->create();
        session()->forget('active_church_id');
        app(TenantContext::class)->forgetResolved();

        try {
            app(ChurchDashboardSnapshotBuilder::class)->build();
            $this->fail('Ambiguous context built a dashboard.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $membership->update(['status' => MembershipStatus::SUSPENDED]);
        app(TenantContext::class)->forgetResolved();
        $this->get('/admin')->assertForbidden();
    }
}

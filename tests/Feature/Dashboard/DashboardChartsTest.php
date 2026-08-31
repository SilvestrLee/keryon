<?php

namespace Tests\Feature\Dashboard;

use App\Dashboard\Charts\DailyChartBuckets;
use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use App\Filament\Pages\ChurchDashboard;
use App\Models\Church;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    public function test_chart_families_compose_from_capabilities_without_a_care_chart(): void
    {
        $this->dashboardActor([ChurchRole::COMMUNICATIONS]);
        $this->allowDashboardEntitlements();

        $charts = app(ChurchDashboardSnapshotBuilder::class)->build()->charts;

        $this->assertSame(['communications', 'campaigns', 'website', 'faithflow'], array_column($charts, 'key'));
        $this->assertNotContains('care', array_column($charts, 'key'));
    }

    public function test_administrator_receives_only_the_governed_congregation_trend(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR]);

        $this->assertSame(['congregation'], array_column(app(ChurchDashboardSnapshotBuilder::class)->build()->charts, 'key'));
    }

    public function test_care_only_membership_runs_no_chart_domain_query(): void
    {
        $this->dashboardActor([ChurchRole::CARE]);
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build();

        $this->assertSame([], $snapshot->charts);
        $queries = implode(' ', $sql);
        $this->assertStringNotContainsString('content_items', $queries);
        $this->assertStringNotContainsString('campaigns', $queries);
        $this->assertStringNotContainsString('website_publications', $queries);
        $this->assertStringNotContainsString('faithflow_usage', $queries);
    }

    public function test_zero_and_short_history_are_truthful_thirty_day_series(): void
    {
        [$church] = $this->dashboardActor([ChurchRole::COMMUNICATIONS]);
        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build();
        $communications = collect($snapshot->charts)->firstWhere('key', 'communications');

        $this->assertFalse($communications->hasData());
        $this->assertCount(30, $communications->series[0]->points);
        $this->assertSame(0, $communications->total());

        DB::table('content_items')->insert([
            'church_id' => $church->id, 'title' => 'Synthetic chart proof', 'content_type' => 'announcement',
            'body' => 'Synthetic test content.', 'status' => 'approved', 'origin' => 'human',
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2), 'approved_at' => now()->subDay(),
        ]);

        app(TenantContext::class)->forgetResolved();
        $communications = collect(app(ChurchDashboardSnapshotBuilder::class)->build()->charts)->firstWhere('key', 'communications');
        $this->assertSame(2, $communications->total());
        $this->assertSame(1, $communications->series[0]->total());
        $this->assertSame(1, $communications->series[1]->total());
    }

    public function test_zero_state_and_accessible_chart_data_render_without_reordering_actions(): void
    {
        $this->dashboardActor([ChurchRole::COMMUNICATIONS]);

        Livewire::test(ChurchDashboard::class)
            ->assertSuccessful()
            ->assertSeeInOrder(['Needs your attention', 'Operational trends', 'Continue your work'])
            ->assertSee('No communications activity yet')
            ->assertDontSee('Care information unavailable');
    }

    public function test_chart_queries_remain_isolated_to_selected_church(): void
    {
        [$church] = $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $other = Church::factory()->create();
        foreach ([$church->id, $other->id] as $churchId) {
            DB::table('congregation_members')->insert([
                'church_id' => $churchId, 'first_name' => 'Synthetic', 'last_name' => 'Person',
                'phone' => '+10000000000', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $chart = app(ChurchDashboardSnapshotBuilder::class)->build()->charts[0];
        $this->assertSame(1, $chart->total());
    }

    public function test_daily_bucketing_uses_church_timezone_and_never_invents_activity(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 12:00:00', 'Africa/Lagos'));
        $points = app(DailyChartBuckets::class)->from([
            CarbonImmutable::parse('2026-08-31 23:30:00', 'UTC'),
        ], 'Africa/Lagos');

        $this->assertCount(30, $points);
        $this->assertSame(1, collect($points)->firstWhere('date', '2026-09-01')->value);
        $this->assertSame(1, array_sum(array_column($points, 'value')));
    }

    public function test_entitlement_denial_suppresses_gated_chart_queries(): void
    {
        $this->dashboardActor([ChurchRole::COMMUNICATIONS]);
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        $keys = array_column(app(ChurchDashboardSnapshotBuilder::class)->build()->charts, 'key');
        $queries = implode(' ', $sql);

        $this->assertSame(['communications', 'campaigns'], $keys);
        $this->assertStringNotContainsString('website_publications', $queries);
        $this->assertStringNotContainsString('faithflow_usage', $queries);
    }

    public function test_broad_chart_composition_has_a_bounded_query_cost(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);
        $this->allowDashboardEntitlements();
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        app(ChurchDashboardSnapshotBuilder::class)->build();

        $this->assertLessThanOrEqual(22, $count);
    }
}

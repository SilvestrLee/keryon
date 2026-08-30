<?php

namespace Tests\Feature\Dashboard;

use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    public function test_narrow_membership_runs_fewer_queries_and_no_care_query(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $count = 0;
        $sql = [];
        DB::listen(function ($query) use (&$count, &$sql): void {
            $count++;
            $sql[] = $query->sql;
        });

        app(ChurchDashboardSnapshotBuilder::class)->build();
        $this->assertLessThanOrEqual(8, $count, implode("\n", $sql));
        $this->assertStringNotContainsString('prayer_requests', implode(' ', $sql));
    }

    public function test_full_capability_snapshot_has_bounded_queries(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);
        $count = 0;
        $sql = [];
        DB::listen(function ($query) use (&$count, &$sql): void {
            $count++;
            $sql[] = $query->sql;
        });

        app(ChurchDashboardSnapshotBuilder::class)->build();
        $this->assertLessThanOrEqual(16, $count, implode("\n", $sql));
    }
}

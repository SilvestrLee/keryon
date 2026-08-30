<?php

namespace Tests\Feature\Dashboard;

use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use App\Models\PrayerRequest;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class DashboardCareIsolationTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    public function test_non_care_membership_never_queries_or_serializes_care(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build()->toArray();
        $this->assertArrayNotHasKey('care', $snapshot['summaries']);
        $this->assertStringNotContainsString('prayer_requests', implode(' ', $queries));
        $this->assertNotContains('care.new', array_column($snapshot['actions'], 'key'));
    }

    public function test_care_member_gets_counts_without_request_content(): void
    {
        $this->dashboardActor([ChurchRole::CARE]);
        PrayerRequest::query()->create(['title' => 'Private title', 'request' => 'Sensitive prayer content']);

        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build()->toArray();
        $this->assertSame(1, $snapshot['summaries']['care'][0]['value']);
        $this->assertContains('care.new', array_column($snapshot['actions'], 'key'));
        $this->assertStringNotContainsString('Sensitive prayer content', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Private title', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_removing_care_removes_projection_on_next_request(): void
    {
        [, , $membership] = $this->dashboardActor([ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);
        $this->assertArrayHasKey('care', app(ChurchDashboardSnapshotBuilder::class)->build()->toArray()['summaries']);

        $membership->roles()->where('role', ChurchRole::CARE)->delete();
        app(TenantContext::class)->forgetResolved();
        $this->assertArrayNotHasKey('care', app(ChurchDashboardSnapshotBuilder::class)->build()->toArray()['summaries']);
    }
}

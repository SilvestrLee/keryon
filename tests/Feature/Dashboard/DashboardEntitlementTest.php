<?php

namespace Tests\Feature\Dashboard;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use App\Enums\EntitlementKey;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class DashboardEntitlementTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    public function test_website_requires_capability_and_entitlement_and_decision_is_memoized(): void
    {
        $this->dashboardActor([ChurchRole::COMMUNICATIONS]);
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->with(Mockery::type(Church::class), EntitlementKey::WebsiteEnabled)->once()->andReturnTrue();
        $resolver->shouldReceive('allows')->andReturnFalse()->byDefault();
        $this->app->instance(EntitlementResolver::class, $resolver);

        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build()->toArray();
        $this->assertContains('website.unpublished', array_column($snapshot['actions'], 'key'));
        $this->assertContains('Website', array_column($snapshot['shortcuts'], 'label'));
    }

    public function test_capability_without_entitlement_suppresses_website(): void
    {
        $this->dashboardActor([ChurchRole::COMMUNICATIONS]);
        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build()->toArray();
        $this->assertNotContains('website.unpublished', array_column($snapshot['actions'], 'key'));
        $this->assertNotContains('Website', array_column($snapshot['shortcuts'], 'label'));
    }

    public function test_entitlement_is_not_looked_up_without_corresponding_capability(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldNotReceive('allows');
        $this->app->instance(EntitlementResolver::class, $resolver);

        app(ChurchDashboardSnapshotBuilder::class)->build();
        $this->assertTrue(true);
    }
}

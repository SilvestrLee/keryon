<?php

namespace Tests\Feature\Communications;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Communications\ChurchCommunicationsSnapshotBuilder;
use App\Enums\ChurchRole;
use App\Enums\EntitlementKey;
use App\Models\Church;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CommunicationsEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_destinations_require_entitlement_but_manual_work_remains(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->with(Mockery::type(Church::class), EntitlementKey::DesignEnabled)->once()->andReturnFalse();
        $resolver->shouldReceive('allows')->with(Mockery::type(Church::class), EntitlementKey::FaithFlowEnabled)->once()->andReturnFalse();
        $resolver->shouldReceive('allows')->with(Mockery::type(Church::class), EntitlementKey::WebsiteEnabled)->once()->andReturnFalse();
        $this->app->instance(EntitlementResolver::class, $resolver);

        $labels = collect(app(ChurchCommunicationsSnapshotBuilder::class)->build()->destinations)->pluck('label')->all();
        $this->assertContains('Campaigns', $labels);
        $this->assertContains('Content', $labels);
        $this->assertNotContains('Designs', $labels);
        $this->assertNotContains('FaithFlow', $labels);
        $this->assertNotContains('Website', $labels);
    }
}

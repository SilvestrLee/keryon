<?php

namespace Tests\Concerns;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Mockery;

trait BuildsDashboardContext
{
    /** @param list<ChurchRole> $roles @return array{Church, User, ChurchMembership} */
    protected function dashboardActor(array $roles, bool $primary = false, array $churchAttributes = []): array
    {
        $church = Church::factory()->create(array_merge([
            'name' => 'Dashboard Church',
            'email' => 'hello@example.test',
            'timezone' => 'Africa/Lagos',
        ], $churchAttributes));
        $user = User::factory()->forChurch($church, $roles, primary: $primary)->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->denyDashboardEntitlements();

        return [$church, $user, $user->memberships()->where('church_id', $church->id)->firstOrFail()];
    }

    protected function denyDashboardEntitlements(): void
    {
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->andReturnFalse()->byDefault();
        $this->app->instance(EntitlementResolver::class, $resolver);
    }

    protected function allowDashboardEntitlements(): void
    {
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->andReturnTrue()->byDefault();
        $this->app->instance(EntitlementResolver::class, $resolver);
    }
}

<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MarketplacePublicExposureTest extends TestCase
{
    public function test_marketplace_routes_remain_inside_authenticated_admin_workspace(): void
    {
        $marketplaceRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains(strtolower($route->uri()), 'marketplace'));

        $this->assertNotEmpty($marketplaceRoutes);
        $this->assertTrue($marketplaceRoutes->every(fn ($route): bool => str_starts_with($route->uri(), 'admin/')));
        $this->assertTrue($marketplaceRoutes->every(fn ($route): bool => in_array('Filament\\Http\\Middleware\\Authenticate', $route->gatherMiddleware(), true)));
        $this->assertTrue($marketplaceRoutes->every(fn ($route): bool => ! str_contains($route->uri(), 'preview') && ! str_contains($route->uri(), 'source')));
    }
}

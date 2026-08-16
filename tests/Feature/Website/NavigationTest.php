<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class NavigationTest extends TestCase
{
    public function test_homepage_navigation_links_to_valid_named_routes(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        foreach (['site.features', 'site.solutions', 'site.pricing', 'site.resources', 'site.about', 'site.book-demo'] as $routeName) {
            $response->assertSee(route($routeName), false);
        }
    }

    public function test_homepage_login_link_points_to_the_admin_panel_login_route(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('filament.admin.auth.login'), false);
    }

    public function test_footer_links_to_valid_named_routes(): void
    {
        $response = $this->get('/');

        foreach (['site.features', 'site.pricing', 'site.about', 'site.book-demo'] as $routeName) {
            $response->assertSee(route($routeName), false);
        }
    }
}

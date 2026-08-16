<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class HomepageTest extends TestCase
{
    public function test_homepage_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_homepage_contains_the_hero_headline(): void
    {
        $response = $this->get('/');

        $response->assertSee("Your Church's Digital Communications Staff Member.", false);
    }

    public function test_homepage_lists_the_four_core_modules(): void
    {
        $response = $this->get('/');

        $response->assertSee('Congregation');
        $response->assertSee('Care Center');
        $response->assertSee('Communications Hub');
        $response->assertSee('Campaigns');
    }
}

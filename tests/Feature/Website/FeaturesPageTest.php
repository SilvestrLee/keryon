<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class FeaturesPageTest extends TestCase
{
    public function test_features_page_returns_a_successful_response(): void
    {
        $response = $this->get('/features');

        $response->assertStatus(200);
    }

    public function test_features_page_contains_its_primary_heading(): void
    {
        $response = $this->get('/features');

        $response->assertSee("Keryon brings the church's communication work into one focused place.", false);
    }

    public function test_features_page_represents_the_four_product_pillars(): void
    {
        $response = $this->get('/features');

        $response->assertSee('Congregation');
        $response->assertSee('Care Center');
        $response->assertSee('Communications Hub');
        $response->assertSee('Campaigns');
    }

    public function test_features_page_offers_the_primary_book_a_demo_cta(): void
    {
        $response = $this->get('/features');

        $response->assertSee(route('site.book-demo'), false);
        $response->assertSee('Book a Demo');
    }

    public function test_features_page_uses_the_public_site_navigation(): void
    {
        $response = $this->get('/features');

        $response->assertSee(route('home'), false);
        $response->assertSee(route('filament.admin.auth.login'), false);
    }

    public function test_features_page_no_longer_renders_the_coming_soon_view(): void
    {
        $response = $this->get('/features');

        $response->assertDontSee('This page is on its way.');
    }

    public function test_features_page_does_not_reference_excluded_product_capabilities(): void
    {
        $response = $this->get('/features');

        foreach ([
            'donation',
            'giving',
            'accounting',
            'attendance',
            'volunteer',
            'payroll',
            'sermon',
        ] as $excludedTerm) {
            $response->assertDontSeeText($excludedTerm);
        }
    }
}

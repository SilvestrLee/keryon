<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class SolutionsPageTest extends TestCase
{
    public function test_solutions_page_returns_a_successful_response(): void
    {
        $response = $this->get('/solutions');

        $response->assertStatus(200);
    }

    public function test_solutions_page_contains_its_primary_heading(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee('Bring scattered church communication work into one focused system.', false);
    }

    public function test_solutions_page_represents_the_congregation_solution(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee('clearer picture of who you\'re reaching', false);
    }

    public function test_solutions_page_represents_the_care_solution(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee('Keep prayer and care needs visible', false);
    }

    public function test_solutions_page_represents_the_communications_solution(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee("Bring the church's content work into one place.", false);
    }

    public function test_solutions_page_represents_the_campaigns_solution(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee('Easter');
        $response->assertSee('Christmas');
        $response->assertSee('Youth Week');
        $response->assertSee('Pastor Appreciation');
    }

    public function test_solutions_page_represents_website_themes_and_custom_design(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee('Keryon Theme');
        $response->assertSee('Custom Website Design');
        $response->assertSee(route('site.themes'), false);
        $response->assertSee(route('site.themes.custom-design'), false);
    }

    public function test_solutions_page_offers_a_book_a_demo_cta(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee('Book a Demo');
        $response->assertSee(route('site.book-demo'), false);
    }

    public function test_solutions_page_links_to_features(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee('Explore Features');
        $response->assertSee(route('site.features'), false);
    }

    public function test_solutions_page_uses_the_public_site_navigation(): void
    {
        $response = $this->get('/solutions');

        $response->assertSee(route('home'), false);
        $response->assertSee(route('filament.admin.auth.login'), false);
    }

    public function test_solutions_page_no_longer_renders_the_coming_soon_view(): void
    {
        $response = $this->get('/solutions');

        $response->assertDontSee('This page is on its way.');
    }

    public function test_solutions_page_does_not_reference_excluded_product_capabilities(): void
    {
        $response = $this->get('/solutions');

        foreach ([
            'donation',
            'giving',
            'accounting',
            'attendance',
            'volunteer',
            'payroll',
            'sermon',
            'ticketing',
        ] as $excludedTerm) {
            $response->assertDontSeeText($excludedTerm);
        }
    }

    public function test_solutions_page_does_not_invent_a_price(): void
    {
        $response = $this->get('/solutions');

        $response->assertDontSee('$');
    }
}

<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class PricingPageTest extends TestCase
{
    public function test_pricing_page_returns_a_successful_response(): void
    {
        $response = $this->get('/pricing');

        $response->assertStatus(200);
    }

    public function test_pricing_page_contains_its_primary_heading(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('One Keryon subscription. The whole communications workspace.', false);
    }

    public function test_pricing_page_represents_the_global_monthly_price(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('$59', false);
    }

    public function test_pricing_page_represents_the_global_annual_price(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('$590', false);
    }

    public function test_pricing_page_represents_the_nigeria_monthly_price(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('₦25,000', false);
    }

    public function test_pricing_page_represents_the_nigeria_annual_price(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('₦250,000', false);
    }

    public function test_pricing_page_represents_the_annual_savings(): void
    {
        $response = $this->get('/pricing');

        $response->assertSeeText('save 2 months', false);
    }

    public function test_pricing_page_represents_the_full_workspace_positioning(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('Congregation');
        $response->assertSee('Care Center');
        $response->assertSee('Communications Hub');
        $response->assertSee('Campaigns');
    }

    public function test_pricing_page_represents_standard_themes_included(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('Standard Website Themes');
        $response->assertSee(route('site.themes'), false);
    }

    public function test_pricing_page_represents_custom_website_design(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('Custom Website Design');
        $response->assertSee('Custom quote', false);
        $response->assertSee(route('site.themes.custom-design'), false);
    }

    public function test_pricing_page_represents_managed_hosting(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('Managed Website Hosting');
    }

    public function test_pricing_page_represents_the_trial_terms(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('21 days', false);
        $response->assertSee('No card required', false);
    }

    public function test_pricing_page_clarifies_no_congregation_size_pricing(): void
    {
        $response = $this->get('/pricing');

        $response->assertSeeText("isn't priced by the number of people");
    }

    public function test_pricing_page_offers_a_book_a_demo_cta(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('Book a Demo');
        $response->assertSee(route('site.book-demo'), false);
    }

    public function test_pricing_page_links_to_features(): void
    {
        $response = $this->get('/pricing');

        $response->assertSee('See all features');
        $response->assertSee(route('site.features'), false);
    }

    public function test_pricing_page_no_longer_renders_the_coming_soon_view(): void
    {
        $response = $this->get('/pricing');

        $response->assertDontSee('This page is on its way.');
    }

    public function test_pricing_page_does_not_present_module_gated_tiers(): void
    {
        $response = $this->get('/pricing');

        $response->assertDontSee('Starter');
        $response->assertDontSee('Growth');
        // Not "Professional" as a bare substring — this page legitimately
        // says "Professionally designed" (K-WEB-005), which contains that
        // substring without being a module-tier name.
        $response->assertDontSee('Professional plan');
        $response->assertDontSee('Professional tier');
    }

    public function test_pricing_page_does_not_expose_unenforced_numeric_limits(): void
    {
        $response = $this->get('/pricing');

        $response->assertDontSee('15 users');
        $response->assertDontSee('10GB');
    }

    public function test_pricing_page_does_not_offer_a_fake_self_serve_trial_cta(): void
    {
        $response = $this->get('/pricing');

        $response->assertDontSee('Start Trial');
    }
}

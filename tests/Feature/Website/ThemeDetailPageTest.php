<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class ThemeDetailPageTest extends TestCase
{
    public function test_proclaim_page_returns_a_successful_response(): void
    {
        $response = $this->get('/themes/proclaim');

        $response->assertStatus(200);
    }

    public function test_proclaim_page_contains_its_theme_identity(): void
    {
        $response = $this->get('/themes/proclaim');

        $response->assertSee('Proclaim');
        $response->assertSee('Bold. Editorial. Message-led.', false);
    }

    public function test_proclaim_page_shows_a_truthful_preview_status(): void
    {
        $response = $this->get('/themes/proclaim');

        $response->assertSee('Preview');
        $response->assertSee('Live demo coming soon');
    }

    public function test_proclaim_page_does_not_offer_a_live_demo_link(): void
    {
        $response = $this->get('/themes/proclaim');

        // No real Proclaim demo exists yet — the page must not claim one does.
        $response->assertDontSee('View Live Demo');
        $response->assertDontSeeText('Activate');
    }

    public function test_proclaim_page_offers_the_custom_design_alternative(): void
    {
        $response = $this->get('/themes/proclaim');

        $response->assertSee(route('site.themes.custom-design'), false);
    }

    public function test_proclaim_page_uses_the_public_site_navigation(): void
    {
        $response = $this->get('/themes/proclaim');

        $response->assertSee(route('home'), false);
        $response->assertSee(route('filament.admin.auth.login'), false);
    }
}

<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class ThemesPageTest extends TestCase
{
    public function test_themes_page_returns_a_successful_response(): void
    {
        $response = $this->get('/themes');

        $response->assertStatus(200);
    }

    public function test_themes_page_contains_its_primary_heading(): void
    {
        $response = $this->get('/themes');

        $response->assertSee('A church website should feel like your church', false);
    }

    public function test_themes_page_represents_proclaim(): void
    {
        $response = $this->get('/themes');

        $response->assertSee('Proclaim');
        $response->assertSee(route('site.themes.proclaim'), false);
    }

    public function test_themes_page_represents_the_custom_design_path(): void
    {
        $response = $this->get('/themes');

        $response->assertSee('Custom Website Design');
        $response->assertSee(route('site.themes.custom-design'), false);
    }

    public function test_themes_page_offers_a_book_demo_or_custom_design_cta(): void
    {
        $response = $this->get('/themes');

        $response->assertSee('Request a Custom Design');
        $response->assertSee(route('site.book-demo'), false);
    }

    public function test_themes_page_uses_the_public_site_navigation(): void
    {
        $response = $this->get('/themes');

        $response->assertSee(route('home'), false);
        $response->assertSee(route('filament.admin.auth.login'), false);
    }
}

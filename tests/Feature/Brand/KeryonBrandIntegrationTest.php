<?php

namespace Tests\Feature\Brand;

use Tests\TestCase;

class KeryonBrandIntegrationTest extends TestCase
{
    public function test_public_navigation_and_footer_use_the_canonical_lockup(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('keryon-lockup', false)
            ->assertSee('aria-label="Keryon"', false)
            ->assertSee('branding/logo/keryon-logo.svg', false);
    }

    public function test_product_font_styles_reference_the_authorized_gilroy_assets(): void
    {
        $brandCss = file_get_contents(resource_path('css/brand.css'));

        $this->assertIsString($brandCss);

        foreach (['Regular', 'Medium', 'Semibold', 'Bold'] as $weight) {
            $this->assertStringContainsString("Gilroy-{$weight}.woff2", $brandCss);
            $this->assertFileExists(resource_path("fonts/gilroy/Gilroy-{$weight}.woff2"));
        }

        $this->assertStringContainsString("--font-keryon: 'Gilroy'", $brandCss);
        $this->assertStringNotContainsString("bunny('Inter'", file_get_contents(base_path('vite.config.js')));
    }

    public function test_authenticated_panel_branding_uses_the_canonical_lockup(): void
    {
        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('keryon-lockup', false)
            ->assertSee('aria-label="Keryon"', false)
            ->assertSee('branding/logo/keryon-logo.svg', false);
    }

    public function test_church_website_typography_contract_remains_separate(): void
    {
        $churchCss = file_get_contents(resource_path('css/public-website.css'));

        $this->assertIsString($churchCss);
        $this->assertStringContainsString('font-family: var(--church-body)', $churchCss);
        $this->assertStringContainsString('font-family: var(--church-heading)', $churchCss);
        $this->assertStringNotContainsString('Gilroy', $churchCss);
    }
}

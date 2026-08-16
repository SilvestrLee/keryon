<?php

namespace Tests\Feature\Website;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ComingSoonPagesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function placeholderRoutes(): array
    {
        return [
            'pricing' => ['/pricing', 'Pricing'],
            'resources' => ['/resources', 'Resources'],
            'about' => ['/about', 'About'],
        ];
    }

    #[DataProvider('placeholderRoutes')]
    public function test_placeholder_page_returns_a_successful_response(string $uri, string $pageTitle): void
    {
        $response = $this->get($uri);

        $response->assertStatus(200);
    }

    #[DataProvider('placeholderRoutes')]
    public function test_placeholder_page_renders_its_own_title(string $uri, string $pageTitle): void
    {
        $response = $this->get($uri);

        $response->assertSee($pageTitle);
    }

    #[DataProvider('placeholderRoutes')]
    public function test_placeholder_page_offers_a_way_forward(string $uri, string $pageTitle): void
    {
        $response = $this->get($uri);

        $response->assertSee('Book a Demo');
    }
}

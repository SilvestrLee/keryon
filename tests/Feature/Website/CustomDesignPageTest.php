<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class CustomDesignPageTest extends TestCase
{
    public function test_custom_design_page_returns_a_successful_response(): void
    {
        $response = $this->get('/themes/custom-design');

        $response->assertStatus(200);
    }

    public function test_custom_design_page_identifies_a_paid_service(): void
    {
        $response = $this->get('/themes/custom-design');

        $response->assertSee('paid service');
        $response->assertSee('custom quote', false);
    }

    public function test_custom_design_page_does_not_invent_a_price(): void
    {
        $response = $this->get('/themes/custom-design');

        $response->assertDontSee('$');
    }

    public function test_custom_design_page_represents_the_process(): void
    {
        $response = $this->get('/themes/custom-design');

        foreach (['Discover', 'Define', 'Design', 'Build', 'Launch'] as $step) {
            $response->assertSee($step);
        }
    }

    public function test_custom_design_page_offers_a_cta(): void
    {
        $response = $this->get('/themes/custom-design');

        $response->assertSee('Discuss a Custom Website');
        $response->assertSee(route('site.book-demo'), false);
    }

    public function test_custom_design_page_does_not_imply_page_builder_functionality(): void
    {
        $response = $this->get('/themes/custom-design');

        foreach (['drag and drop', 'drag-and-drop', 'page builder'] as $term) {
            $response->assertDontSeeText($term);
        }
    }
}

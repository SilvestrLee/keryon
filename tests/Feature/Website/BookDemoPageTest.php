<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

class BookDemoPageTest extends TestCase
{
    public function test_book_demo_page_returns_a_successful_response(): void
    {
        $response = $this->get('/book-demo');

        $response->assertStatus(200);
    }

    public function test_book_demo_page_contains_the_contact_email(): void
    {
        $response = $this->get('/book-demo');

        $response->assertSee('hello@keryon.app');
    }
}

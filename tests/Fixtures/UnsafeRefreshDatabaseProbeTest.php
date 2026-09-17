<?php

namespace Tests\Fixtures;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnsafeRefreshDatabaseProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_body_is_never_reached_for_an_unsafe_database(): void
    {
        file_put_contents((string) getenv('TEST_DATABASE_GUARD_PROBE_MARKER'), 'reached');

        $this->fail('The unsafe test database guard did not execute before RefreshDatabase.');
    }
}

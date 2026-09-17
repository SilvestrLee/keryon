<?php

namespace Tests\Feature\Infrastructure;

use Tests\Support\TestDatabaseSafety;
use Tests\TestCase;

class TestDatabaseSafetyBootTest extends TestCase
{
    public function test_normal_repository_test_configuration_passes_the_runtime_guard(): void
    {
        TestDatabaseSafety::assertSafe($this->app);

        $this->assertSame('testing', $this->app->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}

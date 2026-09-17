<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseSafety;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $application = parent::createApplication();

        TestDatabaseSafety::assertSafe($application);

        return $application;
    }
}

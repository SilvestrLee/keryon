<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabaseSafety;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_sqlite_memory_is_allowed_in_testing(): void
    {
        TestDatabaseSafety::assertConfiguration(
            environment: 'testing',
            connection: 'sqlite',
            driver: 'sqlite',
            database: ':memory:',
        );

        $this->addToAssertionCount(1);
    }

    public function test_explicitly_allowlisted_test_named_persistent_database_is_allowed(): void
    {
        TestDatabaseSafety::assertConfiguration(
            environment: 'testing',
            connection: 'mysql',
            driver: 'mysql',
            database: 'keryon_test',
            allowedPersistentConnection: 'mysql',
            allowedPersistentDatabase: 'keryon_test',
        );

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_configuration_is_rejected_with_bounded_diagnostics(
        string $environment,
        string $connection,
        string $driver,
        string $database,
    ): void {
        try {
            TestDatabaseSafety::assertConfiguration(
                environment: $environment,
                connection: $connection,
                driver: $driver,
                database: $database,
            );
            $this->fail('Unsafe database configuration was accepted.');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString("environment [{$environment}]", $message);
            $this->assertStringContainsString("connection [{$connection}]", $message);
            $this->assertStringContainsString("driver [{$driver}]", $message);
            $this->assertStringContainsString("database [{$database}]", $message);
            $this->assertStringNotContainsString('username', $message);
            $this->assertStringNotContainsString('password', $message);
        }
    }

    public function test_protected_database_name_is_rejected_even_if_explicitly_allowlisted(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database [keryon_staging]');

        TestDatabaseSafety::assertConfiguration(
            environment: 'testing',
            connection: 'mysql',
            driver: 'mysql',
            database: 'keryon_staging',
            allowedPersistentConnection: 'mysql',
            allowedPersistentDatabase: 'keryon_staging',
        );
    }

    public function test_unknown_persistent_database_requires_both_exact_allowlist_values_and_test_naming(): void
    {
        foreach ([
            [null, null],
            ['mysql', null],
            ['mysql', 'scratch'],
        ] as [$allowedConnection, $allowedDatabase]) {
            try {
                TestDatabaseSafety::assertConfiguration(
                    environment: 'testing',
                    connection: 'mysql',
                    driver: 'mysql',
                    database: 'scratch',
                    allowedPersistentConnection: $allowedConnection,
                    allowedPersistentDatabase: $allowedDatabase,
                );
                $this->fail('Unknown persistent database was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('database [scratch]', $exception->getMessage());
            }
        }
    }

    public function test_no_configuration_refresh_database_probe_fails_before_test_body_or_connection(): void
    {
        $root = dirname(__DIR__, 3);
        $marker = sys_get_temp_dir().'/keryon-test-safety-'.bin2hex(random_bytes(8));
        $process = new Process(
            [
                PHP_BINARY,
                $root.'/vendor/bin/phpunit',
                '--no-configuration',
                '--bootstrap',
                $root.'/vendor/autoload.php',
                $root.'/tests/Fixtures/UnsafeRefreshDatabaseProbeTest.php',
            ],
            $root,
            [
                'APP_ENV' => 'local',
                'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => 'keryon',
                'DB_URL' => '',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '1',
                'DB_USERNAME' => 'guard_probe',
                'DB_PASSWORD' => 'guard-secret-never-used',
                'TEST_DATABASE_GUARD_PROBE_MARKER' => $marker,
            ],
        );

        $process->run();
        $output = $process->getOutput().$process->getErrorOutput();

        $this->assertFalse($process->isSuccessful(), $output);
        $this->assertStringContainsString('Unsafe test database configuration', $output);
        $this->assertStringContainsString('environment [local]', $output);
        $this->assertStringContainsString('connection [mysql]', $output);
        $this->assertStringContainsString('database [keryon]', $output);
        $this->assertStringNotContainsString('guard-secret-never-used', $output);
        $this->assertFileDoesNotExist($marker, 'The probe test body ran after an unsafe configuration.');
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function unsafeConfigurations(): array
    {
        return [
            'local mysql development database' => ['local', 'mysql', 'mysql', 'keryon'],
            'staging mysql staging database' => ['staging', 'mysql', 'mysql', 'keryon_staging'],
            'production sqlite memory' => ['production', 'sqlite', 'sqlite', ':memory:'],
            'unknown persistent database' => ['testing', 'pgsql', 'pgsql', 'scratch'],
        ];
    }
}

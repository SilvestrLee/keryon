<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestDatabaseSafety
{
    /** @var list<string> */
    private const PROTECTED_DATABASES = [
        'keryon',
        'keryon_staging',
        'keryon_production',
        'keryon_prod',
    ];

    public static function assertSafe(Application $application): void
    {
        $connectionName = (string) $application['config']->get('database.default', '');
        $connection = $application['db']->connection($connectionName);

        self::assertConfiguration(
            environment: $application->environment(),
            connection: $connectionName,
            driver: (string) $connection->getConfig('driver'),
            database: (string) $connection->getDatabaseName(),
            allowedPersistentConnection: $application['config']->get('database.testing_safety.allowed_persistent_connection'),
            allowedPersistentDatabase: $application['config']->get('database.testing_safety.allowed_persistent_database'),
        );
    }

    public static function assertConfiguration(
        string $environment,
        string $connection,
        string $driver,
        string $database,
        ?string $allowedPersistentConnection = null,
        ?string $allowedPersistentDatabase = null,
    ): void {
        $isTestingEnvironment = $environment === 'testing';
        $isInMemorySqlite = $driver === 'sqlite' && $database === ':memory:';
        $isApprovedPersistentTarget = self::isApprovedPersistentTarget(
            $connection,
            $database,
            $allowedPersistentConnection,
            $allowedPersistentDatabase,
        );

        if ($isTestingEnvironment && ($isInMemorySqlite || $isApprovedPersistentTarget)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Unsafe test database configuration: environment [%s], connection [%s] (driver [%s]), database [%s]. Tests require APP_ENV=testing and either SQLite [:memory:] or an explicitly allowlisted persistent test database.',
            $environment,
            $connection,
            $driver,
            $database,
        ));
    }

    private static function isApprovedPersistentTarget(
        string $connection,
        string $database,
        ?string $allowedConnection,
        ?string $allowedDatabase,
    ): bool {
        if (
            $allowedConnection === null
            || $allowedConnection === ''
            || $allowedDatabase === null
            || $allowedDatabase === ''
            || ! hash_equals($allowedConnection, $connection)
            || ! hash_equals($allowedDatabase, $database)
        ) {
            return false;
        }

        $databaseName = strtolower(pathinfo($database, PATHINFO_FILENAME));

        if (in_array($databaseName, self::PROTECTED_DATABASES, true)) {
            return false;
        }

        return preg_match('/(?:^|[_\-.])test(?:ing|s)?(?:[_\-.]|$)/i', $databaseName) === 1;
    }
}

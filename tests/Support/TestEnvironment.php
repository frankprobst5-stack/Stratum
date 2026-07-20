<?php

declare(strict_types=1);

namespace Tests\Support;

use Stratum\Core\Config;
use Stratum\Core\Database;
use Stratum\Core\MigrationRunner;

/**
 * One Config/Database per test process, shared by every TestCase — building
 * a fresh PDO connection per test would work but is pure overhead. Test
 * isolation comes from TestCase::tearDown() cleaning up what each test
 * created, not from separate connections or a per-test database.
 */
final class TestEnvironment
{
    private static ?Config $config = null;
    private static ?Database $db = null;
    private static ?string $rootDir = null;

    public static function boot(string $rootDir): void
    {
        if (self::$db !== null) {
            return;
        }

        $envFile = $rootDir . '/.env.testing';
        if (!is_file($envFile)) {
            fwrite(STDERR, "Missing {$envFile} — copy .env.testing.example to .env.testing and run "
                . "`docker compose -f docker-compose.test.yml up -d` first.\n");
            exit(1);
        }

        self::$rootDir = $rootDir;
        self::$config = new Config($envFile);
        date_default_timezone_set(self::$config->get('APP_TIMEZONE', 'UTC'));
        self::$db = new Database(self::$config);

        // Idempotent (MigrationRunner::run() skips anything already recorded
        // in core_migrations) — safe to run at the top of every test process
        // against a container that's been left running for fast iteration.
        (new MigrationRunner(self::$db))->runAll($rootDir);
    }

    public static function rootDir(): string
    {
        return self::$rootDir ?? throw new \RuntimeException('TestEnvironment::boot() has not run yet.');
    }

    public static function config(): Config
    {
        return self::$config ?? throw new \RuntimeException('TestEnvironment::boot() has not run yet.');
    }

    public static function db(): Database
    {
        return self::$db ?? throw new \RuntimeException('TestEnvironment::boot() has not run yet.');
    }
}

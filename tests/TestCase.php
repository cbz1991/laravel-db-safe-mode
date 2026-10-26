<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\SafeModeServiceProvider;
use Cabuz\SafeMode\Support\SafeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    // Each test runs in a transaction that is rolled back, so persistent
    // databases (MySQL, MariaDB, PostgreSQL in CI) start clean every time.
    use RefreshDatabase;

    // Laravel doesn't fire CommandStarting/CommandFinished in unit tests by
    // default; real `php artisan` runs do, and the command guards rely on them.
    use WithConsoleEvents;

    protected function getPackageProviders($app): array
    {
        return [SafeModeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Non-production so the guard engages, in strict mode unless a test
        // says otherwise (the test database is empty at boot, which would
        // otherwise pick debug mode).
        $app['config']->set('app.env', 'local');
        $app['config']->set('safe-mode.mode', 'strict');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->databaseConnectionConfig());

        // Give the HTTP guard nothing to allow by default.
        $app['config']->set('safe-mode.outbound.http.allowed_hosts', []);
    }

    /**
     * SQLite in memory by default. CI sets DB_CONNECTION (mysql, mariadb or
     * pgsql) to run the suite against real database servers too, since the
     * write paths differ per driver.
     */
    protected function databaseConnectionConfig(): array
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ];
        }

        return [
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'safe_mode'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /** Insert users directly, as an intentional write. */
    protected function insertUsers(string ...$emails): void
    {
        $this->app->make(SafeMode::class)->allowWrites(function () use ($emails) {
            foreach ($emails as $email) {
                DB::table('users')->insert(['name' => 'Someone', 'email' => $email]);
            }
        });
    }
}

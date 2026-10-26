<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\SafeModeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SafeModeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Non-production so the guard engages.
        $app['config']->set('app.env', 'local');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Give the HTTP guard nothing to allow by default.
        $app['config']->set('safe-mode.outbound.http.allowed_hosts', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}

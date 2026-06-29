<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * TestCase variant for tests that require a live database (in-memory SQLite).
 */
abstract class PersistenceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('ngenius.persist_transactions', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

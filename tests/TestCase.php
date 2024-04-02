<?php

namespace DirectoryTree\Bartender\Tests;

use Orchestra\Testbench\Attributes\WithEnv;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as BaseTestCase;
use DirectoryTree\Bartender\BartenderServiceProvider;
use function Orchestra\Testbench\laravel_migration_path;
use function Orchestra\Testbench\workbench_path;

class TestCase extends BaseTestCase
{
    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(laravel_migration_path('/'));
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [BartenderServiceProvider::class];
    }
}

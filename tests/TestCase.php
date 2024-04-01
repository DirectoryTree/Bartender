<?php

namespace DirectoryTree\Bartender\Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use DirectoryTree\Bartender\BartenderServiceProvider;
use function Orchestra\Testbench\artisan;
use function Orchestra\Testbench\workbench_path;

class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [BartenderServiceProvider::class];
    }
}

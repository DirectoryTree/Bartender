<?php

namespace DirectoryTree\Bartender;

use Illuminate\Support\ServiceProvider;

class BartenderServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(BartenderManager::class);

        $this->app->bind(ProviderRepository::class, UserProviderRepository::class);
        $this->app->bind(ProviderRedirector::class, UserProviderRedirector::class);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations/2024_03_31_000001_add_provider_columns_to_users_table.php' => database_path('migrations/2024_03_31_000001_add_provider_columns_to_users_table.php'),
            __DIR__.'/../database/migrations/2024_10_27_131354_add_provider_token_columns_to_users_table.php' => database_path('migrations/2024_10_27_131354_add_provider_token_columns_to_users_table.php'),
        ], 'bartender-migrations');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [BartenderManager::class];
    }
}

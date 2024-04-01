<?php

namespace DirectoryTree\Bartender;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use DirectoryTree\Bartender\Controllers\AuthController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

class BartenderManager
{
    /**
     * The user model class name.
     */
    protected string $userModel = 'App\Models\User';

    /**
     * The registered handlers.
     */
    protected array $handlers = [];

    /**
     * Set the user model class name.
     */
    public function useUserModel(string $userModel): void
    {
        $this->userModel = $userModel;
    }

    /**
     * Get a new user model instance.
     */
    public function user(): Model
    {
        return new $this->userModel;
    }

    /**
     * Register a new driver handler.
     *
     * @param class-string $handler
     */
    public function register(string $driver, string $handler): void
    {
        $this->handlers[$driver] = $handler;
    }

    /**
     * Redirect the user to the OAuth provider.
     */
    public function redirect(string $driver): RedirectResponse
    {
        return $this->handler($driver)->redirect(
            Socialite::driver($driver), $driver
        );
    }

    /**
     * Handle an OAuth response from the provider.
     */
    public function callback(string $driver): RedirectResponse
    {
        return $this->handler($driver)->callback(
            Socialite::driver($driver), $driver
        );
    }

    /**
     * Get all registered handlers.
     */
    public function handlers(): array
    {
        return $this->handlers;
    }

    /**
     * Get the handler instance for the given driver.
     */
    protected function handler(string $driver): ProviderHandler
    {
        return app($this->handlers[$driver]);
    }

    /**
     * Register the authentication routes.
     */
    public function routes(): void
    {
        Route::name('auth.redirect')
            ->whereIn('driver', array_keys($this->handlers))
            ->get('auth/redirect/{driver}', [AuthController::class, 'redirect']);

        Route::name('auth.callback')
            ->whereIn('driver', array_keys($this->handlers))
            ->get('auth/callback/{driver}', [AuthController::class, 'callback']);
    }
}
<?php

namespace DirectoryTree\Bartender;

use DirectoryTree\Bartender\Controllers\AuthController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

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
    public function setUserModel(string $userModel): void
    {
        $this->userModel = $userModel;
    }

    /**
     * Get the user model class name.
     */
    public function getUserModel(): string
    {
        return $this->userModel;
    }

    /**
     * Register a new driver handler to serve.
     *
     * @param  class-string  $handler
     */
    public function serve(string $driver, string $handler = UserProviderHandler::class): void
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
        Route::name('auth.driver.redirect')
            ->whereIn('driver', array_keys($this->handlers))
            ->get('auth/{driver}/redirect', [AuthController::class, 'redirect']);

        Route::name('auth.driver.callback')
            ->whereIn('driver', array_keys($this->handlers))
            ->match(['get', 'post'], 'auth/{driver}/callback', [AuthController::class, 'callback']);
    }
}

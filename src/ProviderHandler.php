<?php

namespace DirectoryTree\Bartender;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider;

interface ProviderHandler
{
    /**
     * Handle redirecting the user to the OAuth provider.
     */
    public function redirect(Provider $provider, string $driver): RedirectResponse;

    /**
     * Handle an OAuth response from the provider.
     */
    public function callback(Provider $provider, string $driver): RedirectResponse;
}

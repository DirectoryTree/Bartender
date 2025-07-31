<?php

namespace DirectoryTree\Bartender;

use Laravel\Socialite\Contracts\Provider;

interface ProviderHandler
{
    /**
     * Handle redirecting the user to the OAuth provider.
     */
    public function redirect(Provider $provider, string $driver): mixed;

    /**
     * Handle an OAuth response from the provider.
     */
    public function callback(Provider $provider, string $driver): mixed;
}

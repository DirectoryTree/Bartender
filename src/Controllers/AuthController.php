<?php

namespace DirectoryTree\Bartender\Controllers;

use Illuminate\Http\RedirectResponse;
use DirectoryTree\Bartender\Facades\Bartender;

class AuthController
{
    /**
     * Handle redirecting the user to the OAuth provider.
     */
    public function redirect(string $driver): RedirectResponse
    {
        return Bartender::redirect($driver);
    }

    /**
     * Handle an OAuth response from the provider.
     */
    public function callback(string $driver): RedirectResponse
    {
        return Bartender::callback($driver);
    }
}

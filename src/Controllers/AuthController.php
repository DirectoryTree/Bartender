<?php

namespace DirectoryTree\Bartender\Controllers;

use DirectoryTree\Bartender\Facades\Bartender;
use Illuminate\Http\RedirectResponse;

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

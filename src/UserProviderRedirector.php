<?php

namespace DirectoryTree\Bartender;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Two\User as SocialiteUser;

class UserProviderRedirector implements ProviderRedirector
{
    /**
     * Redirect when unable to authenticate the user.
     */
    public function unableToAuthenticateUser(Exception $e, string $driver): RedirectResponse
    {
        report($e);

        $message = sprintf('There was a problem creating your %s account. Please try again.', ucfirst($driver));

        return $this->redirector()->to('login')->with('flash', [
            'bannerStyle' => 'danger',
            'banner' => $message,
        ]);
    }

    /**
     * Redirect when the user already exists.
     */
    public function userAlreadyExists(SocialiteUser $user, string $driver): RedirectResponse
    {
        $message = sprintf("An account with the email address '%s' already exists.", $user->email);

        return $this->redirector()->to('login')->with('flash', [
            'bannerStyle' => 'danger',
            'banner' => $message,
        ]);
    }

    /**
     * Redirect when unable to create the user.
     */
    public function unableToCreateUser(Exception $e, SocialiteUser $user, string $driver): RedirectResponse
    {
        report($e);

        return $this->redirector()->to('login')->with('flash', [
            'bannerStyle' => 'danger',
            'banner' => 'There was a problem creating your account. Please try again.',
        ]);
    }

    /**
     * Handle when the user has been successfully authenticated.
     */
    public function userAuthenticated(Authenticatable $user, SocialiteUser $socialite, string $driver): RedirectResponse
    {
        Auth::login($user);

        session()->regenerate();

        return redirect('dashboard');
    }

    /**
     * Create a new redirector instance.
     */
    protected function redirector(): Redirector
    {
        return redirect();
    }
}

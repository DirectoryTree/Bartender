<?php

namespace DirectoryTree\Bartender;

use DirectoryTree\Bartender\Events\UserAuthenticated;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UserProviderRedirector implements ProviderRedirector
{
    /**
     * The URL to redirect to when unable to authenticate the user.
     */
    public static string $unableToAuthenticateUserUrl = 'login';

    /**
     * The URL to redirect to when the user already exists.
     */
    public static string $userAlreadyExistsUrl = 'login';

    /**
     * The URL to redirect to when unable to create the user.
     */
    public static string $unableToCreateUserUrl = 'login';

    /**
     * The URL to redirect to when the user has been successfully authenticated.
     */
    public static string $userAuthenticatedUrl = 'dashboard';

    /**
     * Redirect when unable to authenticate the user.
     */
    public function unableToAuthenticateUser(Exception $e, string $driver): RedirectResponse
    {
        report($e);

        $message = sprintf('There was a problem creating your %s account. Please try again.', ucfirst($driver));

        return $this->redirector()->to(static::$unableToAuthenticateUserUrl)->with('flash', [
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

        return $this->redirector()->to(static::$userAlreadyExistsUrl)->with('flash', [
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

        return $this->redirector()->to(static::$unableToCreateUserUrl)->with('flash', [
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

        Session::regenerate();

        Event::dispatch(new UserAuthenticated($user));

        return redirect()->intended(static::$userAuthenticatedUrl);
    }

    /**
     * Create a new redirector instance.
     */
    protected function redirector(): Redirector
    {
        return redirect();
    }
}

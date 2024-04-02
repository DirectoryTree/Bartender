<?php

namespace DirectoryTree\Bartender;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;

class UserProviderHandler implements ProviderHandler
{
    /**
     * Constructor.
     */
    public function __construct(
        protected ProviderQuery $users,
        protected ProviderRedirector $redirector,
    ) {
    }

    /**
     * Handle redirecting the user to the OAuth provider.
     */
    public function redirect(Provider $provider, string $driver): RedirectResponse
    {
        return $provider->redirect();
    }

    /**
     * Handle an OAuth response from the provider.
     */
    public function callback(Provider $provider, string $driver): RedirectResponse
    {
        try {
            /** @var SocialiteUser $socialite */
            $socialite = $provider->user();
        } catch (Exception $e) {
            return $this->redirector->unableToAuthenticateUser($e, $driver);
        }

        if ($this->users->exists($driver, $socialite)) {
            return $this->redirector->userAlreadyExists($socialite, $driver);
        }

        try {
            $user = $this->users->updateOrCreate($driver, $socialite);
        } catch (Exception $e) {
            return $this->redirector->unableToCreateUser($e, $socialite, $driver);
        }

        Auth::login($user);

        return $this->redirector->userAuthenticated($user, $socialite, $driver);
    }
}
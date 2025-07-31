<?php

namespace DirectoryTree\Bartender;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Contracts\User as SocialiteUser;

interface ProviderRedirector
{
    /**
     * Redirect when unable to authenticate the user.
     */
    public function unableToAuthenticateUser(Exception $e, string $driver): mixed;

    /**
     * Redirect when the user already exists.
     */
    public function userAlreadyExists(SocialiteUser $user, string $driver): mixed;

    /**
     * Redirect when unable to create the user.
     */
    public function unableToCreateUser(Exception $e, SocialiteUser $user, string $driver): mixed;

    /**
     * Handle when the user has been successfully authenticated.
     */
    public function userAuthenticated(Authenticatable $user, SocialiteUser $socialite, string $driver): mixed;
}

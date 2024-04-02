<?php

namespace DirectoryTree\Bartender;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Two\User as SocialiteUser;

interface ProviderQuery
{
    /**
     * Determine if a user with the same email already exists.
     */
    public function exists(string $driver, SocialiteUser $user): bool;

    /**
     * Update or create a user from the given socialite user.
     */
    public function updateOrCreate(string $driver, SocialiteUser $user): Authenticatable;
}
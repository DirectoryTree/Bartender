<?php

namespace DirectoryTree\Bartender;

use DirectoryTree\Bartender\Facades\Bartender;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UserProviderRepository implements ProviderRepository
{
    /**
     * Determine whether the email address belongs to a different identity.
     */
    public function exists(string $driver, SocialiteUser $user): bool
    {
        $providerId = (string) $user->getId();

        if (blank($providerId)) {
            throw new AuthenticationException('The provider did not return a user ID.');
        }

        return $this->newUserQuery(Bartender::getUserModel())
            ->where('email', $user->getEmail())
            ->get(['provider_name', 'provider_id'])
            ->contains(fn ($account) => (
                $account->provider_name !== $driver
                || (string) $account->provider_id !== $providerId
            ));
    }

    /**
     * Update or create the socialite user.
     */
    public function updateOrCreate(string $driver, SocialiteUser $user): Authenticatable
    {
        $providerId = (string) $user->getId();

        if ($this->exists($driver, $user)) {
            throw new AuthenticationException('The email address belongs to another identity.');
        }

        $model = Bartender::getUserModel();

        $eloquent = $this->newUserQuery($model)->firstWhere([
            'provider_name' => $driver,
            'provider_id' => $providerId,
        ]);

        if ($eloquent && ($eloquent->provider_name !== $driver || (string) $eloquent->provider_id !== $providerId)) {
            throw new AuthenticationException('The provider identity does not exactly match the existing account.');
        }

        $eloquent ??= (new $model)->forceFill([
            'email' => $user->getEmail(),
            'provider_name' => $driver,
        ]);

        $eloquent->forceFill(
            array_merge([
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'provider_id' => $providerId,
                'password' => $eloquent->password ?? $this->hash($this->getNewPassword()),
            ],
                $this->isUsingSoftDeletes($model)
                    ? ['deleted_at' => null]
                    : [],
                $this->isVerifyingEmails($model)
                    ? ['email_verified_at' => $eloquent->email_verified_at ?? now()]
                    : [],
                $this->isStoringTokens($model)
                    ? [
                        'provider_access_token' => $user->token,
                        'provider_refresh_token' => $this->getRefreshToken($user, $eloquent->provider_refresh_token),
                    ] : [],
            )
        )->save();

        return $eloquent;
    }

    /**
     * Hash the given value.
     */
    protected function hash(string $value): string
    {
        return Hash::make($value);
    }

    /**
     * Get a new password for the user.
     */
    protected function getNewPassword(): string
    {
        return Str::random();
    }

    /**
     * Get the refresh token from the Socialite user.
     */
    protected function getRefreshToken(SocialiteUser $user, ?string $default = null): ?string
    {
        return $user->refreshToken
            ?? $user->tokenSecret
            ?? $default;
    }

    /**
     * Get a new user query instance.
     *
     * @param  class-string  $model
     */
    protected function newUserQuery(string $model): Builder
    {
        if ($this->isUsingSoftDeletes($model)) {
            return $model::withTrashed();
        }

        return $model::query();
    }

    /**
     * Determine if the given model is storing Socialite tokens.
     */
    protected function isStoringTokens(string $model): bool
    {
        return in_array(StoresProviderTokens::class, class_implements($model));
    }

    /**
     * Determine if the given model is using soft deletes.
     */
    protected function isUsingSoftDeletes(string $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * Determine if the given model is verifying emails.
     */
    protected function isVerifyingEmails(string $model): bool
    {
        return in_array(MustVerifyEmail::class, class_uses_recursive($model));
    }
}

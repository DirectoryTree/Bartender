<?php

namespace DirectoryTree\Bartender;

use DirectoryTree\Bartender\Facades\Bartender;
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
     * Determine if the user already exists under a different provider.
     */
    public function exists(string $driver, SocialiteUser $user): bool
    {
        return $this->newUserQuery(Bartender::getUserModel())
            ->where('email', '=', $user->email)
            ->where(fn (Builder $query) => (
                $query
                    ->whereNull('provider_name')
                    ->orWhere('provider_name', '!=', $driver)
            ))
            ->exists();
    }

    /**
     * Update or create the socialite user.
     */
    public function updateOrCreate(string $driver, SocialiteUser $user): Authenticatable
    {
        $model = Bartender::getUserModel();

        $eloquent = $this->newUserQuery($model)->firstWhere([
            'email' => $user->email,
            'provider_name' => $driver,
        ]) ?? (new $model)->forceFill([
            'email' => $user->email,
            'provider_name' => $driver,
        ]);

        $eloquent->forceFill(
            array_merge([
                'name' => $user->name,
                'provider_id' => $user->id,
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

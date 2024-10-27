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
                'provider_access_token' => $user->token,
                'provider_refresh_token' => $user->refreshToken,
                'password' => $eloquent->password ?? $this->hash($this->getNewPassword()),
            ],
                $this->isUsingSoftDeletes($model)
                    ? ['deleted_at' => null]
                    : [],
                $this->isVerifyingEmails($model)
                    ? ['email_verified_at' => $eloquent->email_verified_at ?? now()]
                    : []
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
     * Determine if the given model uses soft deletes.
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

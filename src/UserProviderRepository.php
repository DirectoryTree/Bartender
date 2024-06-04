<?php

namespace DirectoryTree\Bartender;

use DirectoryTree\Bartender\Facades\Bartender;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
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
                'password' => $eloquent->password ?? bcrypt(Str::random()),
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
     * Get a new user query instance.
     *
     * @param class-string $model
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
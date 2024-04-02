<?php

namespace DirectoryTree\Bartender;

use DirectoryTree\Bartender\Facades\Bartender;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as SocialiteUser;

class UserProviderQuery implements ProviderQuery
{
    /**
     * Determine if a user with the same email already exists.
     */
    public function exists(string $driver, SocialiteUser $user): bool
    {
        return $this->newUserQuery()
            ->where('email', '=', $user->email)
            ->where('provider_id', '!=', $user->id)
            ->where('provider_name', '!=', $driver)
            ->exists();
    }

    /**
     * Update or create a user from the given socialite user.
     */
    public function updateOrCreate(string $driver, SocialiteUser $user): Authenticatable
    {
        /** @var Authenticatable */
        return $this->newUserQuery()->updateOrCreate([
            'provider_id' => $user->id,
            'provider_name' => $driver,
        ], array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => now(),
            'password' => bcrypt(Str::random(32)),
        ], $this->isUsingSoftDeletes(Bartender::user()) ? [
            'deleted_at' => null,
        ] : []));
    }

    /**
     * Get a new user query instance.
     */
    protected function newUserQuery(): Builder
    {
        $model = Bartender::user();

        if ($this->isUsingSoftDeletes($model)) {
            return $model->withTrashed();
        }

        return $model->newQuery();
    }

    /**
     * Determine if the given model uses soft deletes.
     */
    protected function isUsingSoftDeletes(Model $model): bool
    {
        return method_exists($model, 'trashed');
    }
}
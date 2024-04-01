<?php

namespace DirectoryTree\Bartender;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use DirectoryTree\Bartender\Facades\Bartender;

class UserProviderHandler implements ProviderHandler
{
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
            return $this->handleUnableToAuthenticateUser($e, $driver);
        }

        if ($this->userAlreadyExists($socialite)) {
            return $this->handleUserAlreadyExists($socialite, $driver);
        }

        try {
            $user = $this->updateOrCreateUser($driver, $socialite);
        } catch (Exception $e) {
            return $this->handleUnableToCreateUser($e, $socialite, $driver);
        }

        Auth::login($user);

        return $this->handleUserAuthenticated($user);
    }

    /**
     * Handle an exception when unable to authenticate the user.
     */
    protected function handleUnableToAuthenticateUser(Exception $e, string $driver): RedirectResponse
    {
        $message = sprintf('There was a problem creating your %s account. Please try again.', ucfirst($driver));

        return redirect('login')->with('message', $message);
    }

    /**
     * Handle when the user already exists.
     */
    protected function handleUserAlreadyExists(SocialiteUser $user, string $driver): RedirectResponse
    {
        $message = sprintf("An account with the email address '%s' already exists.", $user->email);

        return redirect('login')->with('message', $message);
    }

    /**
     * Handle when unable to create the user.
     */
    protected function handleUnableToCreateUser(Exception $e, SocialiteUser $user, string $driver): RedirectResponse
    {
        return redirect('login')->with('message', 'There was a problem creating your account. Please try again.');
    }

    /**
     * Handle when the user has been successfully authenticated.
     */
    protected function handleUserAuthenticated(Authenticatable $user): RedirectResponse
    {
        return redirect('dashboard');
    }

    /**
     * Determine if a user with the same email already exists.
     */
    protected function userAlreadyExists(SocialiteUser $user): bool
    {
        return $this->newUserQuery()
            ->where('email', '=', $user->email)
            ->where('provider_id', '!=', $user->id)
            ->exists();
    }

    /**
     * Update or create the socialite user.
     */
    protected function updateOrCreateUser(string $driver, SocialiteUser $user): Authenticatable
    {
        return $this->newUserQuery()->updateOrCreate([
            'provider_id' => $user->id,
        ], array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'provider_name' => $driver,
            'email_verified_at' => now(),
            'password' => bcrypt(Str::random(32)),
        ], $this->isUsingSoftDeletes(Bartender::user()) ? [
            'deleted_at' => null,
        ] : []));
    }

    /**
     * Get a new user query instance.
     */
    protected function newUserQuery(): EloquentBuilder|QueryBuilder
    {
        $model = Bartender::user();

        if ($this->isUsingSoftDeletes($model)) {
            return $model->withTrashed();
        }

        return $model->newQuery();
    }

    /**
     * Determine if the model is using soft-deletes.
     */
    protected function isUsingSoftDeletes(Model $model): bool
    {
        return method_exists($model, 'trashed');
    }
}
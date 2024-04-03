<p align="center">
<img src="https://github.com/DirectoryTree/Bartender/blob/master/art/logo.svg" width="200">
</p>

<p align="center">
An opinionated way to authenticate users using Laravel Socialite.
</p>

<p align="center">
<a href="https://github.com/directorytree/bartender/actions" target="_blank"><img src="https://img.shields.io/github/actions/workflow/status/directorytree/bartender/run-tests.yml?branch=master&style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/bartender" target="_blank"><img src="https://img.shields.io/packagist/v/directorytree/bartender.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/bartender" target="_blank"><img src="https://img.shields.io/packagist/dt/directorytree/bartender.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/bartender" target="_blank"><img src="https://img.shields.io/packagist/l/directorytree/bartender.svg?style=flat-square"/></a>
</p>

---

## Index

- [Requirements](#requirements)
- [Installation](#installation)
- [Setup](#setup)
- [Usage](#usage)

## Requirements

- PHP >= 8.0
- Laravel >= 9.0
- Laravel Socialite >= 5.0

## Installation

You can install the package via composer:

```bash
composer require directorytree/bartender
```

Then, publish the migration:

> This will create a `provider_id` and `provider_name` column on the `users` table.

```bash
php artisan vendor:publish --provider="DirectoryTree\Bartender\BartenderServiceProvider"
```

Finally, run the migration:

```bash
php artisan migrate
```

## Setup

Register the authentication routes:

> This will register the `/auth/{driver}/redirect` and `/auth/{driver}/callback` routes.

```php
// routes/web.php

use DirectoryTree\Bartender\Facades\Bartender;

Bartender::routes();
```

Set up your [Socialite Providers](https://socialiteproviders.com/) in your `services.php` configuration file:

```php
// config/services.php

return [
    // ...

    'google' => ['...'],
    
    'microsoft' => ['...'],
];
```

Register the Socialite Provider in your `AuthServiceProvider`:

```php
// app/Providers/AuthServiceProvider.php

use DirectoryTree\Bartender\Bartender;

class AuthServiceProvider extends ServiceProvider
{
    // ...

    public function boot()
    {
        Bartender::serve('google');
        Bartender::serve('microsoft');
    }
}
```

If your application uses a `User` model outside the `App\Models` namespace, you can set it using the `Bartender` facade:

```php
// app/Providers/AuthServiceProvider.php

use App\User;
use DirectoryTree\Bartender\Facades\Bartender;

class AuthServiceProvider extends ServiceProvider
{
    // ...

    public function boot()
    {
        Bartender::setUserModel(User::class);
    }
}
```

## Usage

Direct your user to the `/auth/{driver}/redirect` route to authenticate with the given driver:

```blade
<a href="{{ route('auth.driver.redirect', 'google') }}">
    Login with Google
</a>

<a href="{{ route('auth.driver.redirect', 'microsoft') }}">
    Login with Microsoft
</a>
```

Once the user successfully authenticates, they will be redirected to the `/auth/{driver}/callback` 
route, and the users account will automatically be created or updated.

## Extending & Customizing

Almost everything can be swapped out in Bartender.

If you would like to handle everything yourself for OAuth redirects and callbacks, you may create your own `ProviderHandler`:

```php
namespace App\Socialite;

use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Provider;
use DirectoryTree\Bartender\ProviderHandler;

class UserProviderHandler implements ProviderHandler
{
    /**
     * Constructor.
     */
    public function __construct(
        protected Request $request
    ) {
    }

    /**
     * Handle redirecting the user to the OAuth provider.
     */
    public function redirect(Provider $provider, string $driver): RedirectResponse
    {
        // Perform additional logic here...
    
        return $provider->redirect();
    }

    /**
     * Handle an OAuth response from the provider.
     */
    public function callback(Provider $provider, string $driver): RedirectResponse
    {
        // Authenticate the user your own way...
    
        return redirect()->route('dashboard');
    }
}
```

Then, provide it into the second argument in the `Bartender::serve` method:

```php
// app/Providers/AuthServiceProvider.php

use App\Socialite\UserProviderHandler;
use DirectoryTree\Bartender\Facades\Bartender;

class AuthServiceProvider extends ServiceProvider
{
    // ...

    public function boot()
    {
        Bartender::serve('google', UserProviderHandler::class);
        Bartender::serve('microsoft', UserProviderHandler::class);
    }
}
```

### User Creation & Updating

If you would like to customize the creation of the user in the default
handler, you may create your own `ProviderRepository` implementation:

```php
namespace App\Socialite;

use Illuminate\Contracts\Auth\Authenticatable;
use DirectoryTree\Bartender\ProviderRepository;
use Laravel\Socialite\Two\User as SocialiteUser;

class UserProviderRepository implements ProviderRepository
{
    /**
     * Determine if the user already exists under a different provider.
     */
    public function exists(string $driver, SocialiteUser $user): bool
    {
        // ...
    }

    /**
     * Update or create the socialite user.
     */
    public function updateOrCreate(string $driver, SocialiteUser $user): Authenticatable
    {
        // ...
    }
}
```

Then, bind your implementation in the service container in your `AppServiceProvider`:

```php
namespace App\Providers;

use App\Socialite\UserProviderRepository;
use DirectoryTree\Bartender\ProviderRepository;

class AppServiceProvider extends ServiceProvider
{
    // ...

    public function register()
    {
        $this->app->bind(ProviderRepository::class, UserProviderRepository::class);
    }
}
```

### User Redirects & Flash Messaging

If you would like to customize the behavior of the redirects of the default 
redirector and flash messages depending on the outcome of a OAuth callback, 
you can create your own `ProviderRedirector` implementation:

```php
namespace App\Socialite;

class UserProviderRedirector implements ProviderRedirector
{
    /**
     * Redirect when unable to authenticate the user.
     */
    public function unableToAuthenticateUser(Exception $e, string $driver): RedirectResponse
    {
        return redirect()->route('login')->with('error', 'Unable to authenticate user.');
    }

    /**
     * Redirect when the user already exists.
     */
    public function userAlreadyExists(SocialiteUser $user, string $driver): RedirectResponse
    {
        return redirect()->route('login')->with('error', 'User already exists.');
    }

    /**
     * Redirect when unable to create the user.
     */
    public function unableToCreateUser(Exception $e, SocialiteUser $user, string $driver): RedirectResponse
    {
        return redirect()->route('login')->with('error', 'Unable to create user.');
    }

    /**
     * Handle when the user has been successfully authenticated.
     */
    public function userAuthenticated(Authenticatable $user, SocialiteUser $socialite, string $driver): RedirectResponse
    {
        Auth::login($user);
    
        return redirect()->route('dashboard');
    }
}
```

Then, bind your implementation in the service container in your `AppServiceProvider`:

```php
namespace App\Providers;

use App\Socialite\UserProviderRedirector;

class AppServiceProvider extends ServiceProvider
{
    // ...

    public function register()
    {
        $this->app->bind(ProviderRedirector::class, UserProviderRedirector::class);
    }
}
```

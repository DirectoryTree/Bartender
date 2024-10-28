<p align="center">
<img src="https://github.com/DirectoryTree/Bartender/blob/master/art/logo.svg" width="250">
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

Bartender serves you a controller, routes, and a default implementation for handling authentication with Laravel Socialite providers.

Almost everything in Bartender can be customized.

## Index

- [Requirements](#requirements)
- [Installation](#installation)
- [Setup](#setup)
- [Usage](#usage)
  - [Soft Deletes](#soft-deletes)
  - [Email Verification](#email-verification)
  - [Access/Refresh Tokens](#accessrefresh-tokens)
- [Extending & Customizing](#extending--customizing)

## Requirements

- PHP >= 8.0
- Laravel >= 9.0
- Laravel Socialite >= 5.0

## Installation

You can install the package via composer:

```bash
composer require directorytree/bartender
```

Then, publish the migrations. They will create the required columns on the `users` table:

- `provider_id`
- `provider_name`
- `provider_access_token`
- `provider_refresh_token`

> If your application does not need to store/access provider tokens, you may delete the `2024_10_27_131354_add_provider_token_columns_to_users_table.php` migration.

```bash
php artisan vendor:publish --provider="DirectoryTree\Bartender\BartenderServiceProvider"
```

Finally, run the migrations:

```bash
php artisan migrate
```

## Setup

Register the authentication routes using `Bartender::routes()`.

This will register the `/auth/{driver}/redirect` and `/auth/{driver}/callback` routes.

```php
// routes/web.php

use DirectoryTree\Bartender\Facades\Bartender;

Bartender::routes();
```

Set up any [Socialite Providers](https://socialiteproviders.com) you need, and update your `services.php` configuration file with the `redirect` URL for each provider:

> [!important]
> Remember to fully complete the installation steps for each Socialite Provider you wish to use.
> 
> If you receive a `Driver [X] not supported` exception, you have not completed the installation steps for the provider.

```php
// config/services.php

return [
    // ...

    'google' => [
        // ...
        'redirect' => '/auth/google/callback',
    ],
    
    'microsoft' => [
        // ...
        'redirect' => '/auth/microsoft/callback',
    ],
];
```

Finally, register the Socialite Provider in your `AppServiceProvider` using `Bartender::serve()`:

```php
// app/Providers/AppServiceProvider.php

use DirectoryTree\Bartender\Facades\Bartender;

class AppServiceProvider extends ServiceProvider
{
    // ...

    public function boot(): void
    {
        Bartender::serve('google');
        Bartender::serve('microsoft');
    }
}
```

If your application uses a `User` model outside the default `App\Models` namespace, you can set it using the `Bartender` facade.

> If your application uses the default Laravel `User` model in the `App\Models` namespace, skip this step.

```php
// app/Providers/AuthServiceProvider.php

use App\User;
use DirectoryTree\Bartender\Facades\Bartender;

class AuthServiceProvider extends ServiceProvider
{
    // ...

    public function boot(): void
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
route, which will automatically create or update their application user account.

> [!important]
> If you receive a `Routing requirement for "driver" cannot be empty` exception upon clicking
> one of the login links, you have forgotten to register your the Socialite provider with
> Bartender using `Bartender::serve()` in your `AppServiceProvider`.

### Soft Deletes

With the default `UserProviderRepository`, users will be restored if they are soft-deleted and the login with their provider.

To change this behaviour, [swap out the repository](#user-creation--updating).

### Email Verification

With the default `UserProviderRepository`, users with emails will be automatically verified (via the `email_verified_at` column) if it is not already set.

To change this behaviour, [swap out the repository](#user-creation--updating).

### Access/Refresh Tokens

To enable storing the authentication provider access and refresh tokens 
on your user so that you can access them later, you may apply the
`StoresProviderTokens` interface on your model:

```php
// app/Models/User.php

namespace App\Models;

use DirectoryTree\Bartender\StoresProviderTokens;

class User extends Authenticatable implements StoresProviderTokens
{
    // ...    
}
```

You may also want to add these columns to your model's `$hidden` attributes, as well as `encrypted` casts for additional security:

```php
// app/Models/User.php

class User extends Authenticatable implements StoresProviderTokens
{
    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'provider_access_token',
        'provider_refresh_token'
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'provider_access_token' => 'encrypted',
            'provider_refresh_token' => 'encrypted',
        ];
    }
}
```

Otherwise, if you do not need to store these tokens, you are free to delete the 
published `2024_10_27_131354_add_provider_token_columns_to_users_table.php` 
migration file and omit applying the `StoresProviderTokens` interface.
Bartender will skip storing these tokens as it does not
require them to successfully authenticate users.

## Extending & Customizing

Almost everything can be swapped out in Bartender.

If you would like to handle everything yourself for OAuth redirects and callbacks, you may create your own `ProviderHandler`:

```php
// app/Socialite/UserProviderHandler.php

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

namespace App\Providers;

use App\Socialite\UserProviderHandler;
use DirectoryTree\Bartender\Facades\Bartender;

class AuthServiceProvider extends ServiceProvider
{
    // ...

    public function boot(): void
    {
        Bartender::serve('google', UserProviderHandler::class);
        Bartender::serve('microsoft', UserProviderHandler::class);
    }
}
```

You may also extend the built-in `UserProviderHandler` implementation if you prefer.

For example, if you need to adjust the scopes for a single provider:

```php
// app/Socialite/MicrosoftUserHandler.php

namespace App\Socialite;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider;
use DirectoryTree\Bartender\UserProviderHandler;

class MicrosoftUserHandler extends UserProviderHandler
{
    /**
     * Handle redirecting the user to Microsoft.
     */
    public function redirect(Provider $provider, string $driver): RedirectResponse
    {
        $provider->scopes([
            'Mail.ReadWrite',
            // ...
        ]);
    
        return parent::redirect($provider, $driver);
    }
}
```

Then register it as the handler:

```php
Bartender::serve('microsoft', MicrosoftUserHandler::class);
```

### User Creation & Updating

If you would like to customize the creation of the user in the default
handler, you may create your own `ProviderRepository` implementation:

```php
// app/Socialite/UserProviderRepository.php

namespace App\Socialite;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use DirectoryTree\Bartender\ProviderRepository;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UserProviderRepository implements ProviderRepository
{
    /**
     * Determine if the user already exists under a different provider.
     */
    public function exists(string $driver, SocialiteUser $user): bool
    {
        return User::withTrashed()->where('...')->exists();
    }

    /**
     * Update or create the socialite user.
     */
    public function updateOrCreate(string $driver, SocialiteUser $user): Authenticatable
    {
        $user = User::withTrashed()->firstOrNew([
            // ...
        ]);
        
        return $user;
    }
}
```

Then, bind your implementation in the service container in your `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Socialite\UserProviderRepository;
use DirectoryTree\Bartender\ProviderRepository;

class AppServiceProvider extends ServiceProvider
{
    // ...

    public function register(): void
    {
        $this->app->bind(ProviderRepository::class, UserProviderRepository::class);
    }
}
```

### User Redirects & Flash Messaging

If you would like to customize the behavior of the redirects of the default 
redirector and flash messages depending on the outcome of a OAuth callback, 
you can create your own `ProviderRedirector` implementation:

> It's recommended to regenerate the session after authentication to prevent users
> from exploiting a [session fixation attack](https://laravel.com/docs/11.x/session#regenerating-the-session-id).

```php
// app/Socialite/UserProviderRedirector.php

namespace App\Socialite;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class UserProviderRedirector implements ProviderRedirector
{
    /**
     * Redirect when unable to authenticate the user.
     */
    public function unableToAuthenticateUser(Exception $e, string $driver): RedirectResponse
    {
        report($e);

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
        report($e);

        return redirect()->route('login')->with('error', 'Unable to create user.');
    }

    /**
     * Handle when the user has been successfully authenticated.
     */
    public function userAuthenticated(Authenticatable $user, SocialiteUser $socialite, string $driver): RedirectResponse
    {
        Auth::login($user);
        
        Session::regenerate();
    
        return redirect()->route('dashboard');
    }
}
```

Then, bind your implementation in the service container in your `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Socialite\UserProviderRedirector;

class AppServiceProvider extends ServiceProvider
{
    // ...

    public function register(): void
    {
        $this->app->bind(ProviderRedirector::class, UserProviderRedirector::class);
    }
}
```

<?php

use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\ProviderRedirector;
use DirectoryTree\Bartender\ProviderRepository;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;

it('can redirect to provider', function () {
    $provider = mock(Provider::class);

    $provider->shouldReceive('redirect')->andReturn($redirect = redirect('/'));

    expect(app(UserProviderHandler::class)->redirect($provider, 'foo'))->toBe($redirect);
});

it('can handle exception when user cannot be authenticated', function () {
    $provider = mock(Provider::class);

    $provider->shouldReceive('user')->once()->andThrow(Exception::class);

    $this->mock(ProviderRedirector::class, function ($mock) {
        $mock->shouldReceive('unableToAuthenticateUser')->once()->andReturn(redirect('/'));
    });

    app(UserProviderHandler::class)->callback($provider, 'foo');
});

it('can handle when user already exists', function () {
    $provider = $this->mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn(new SocialiteUser);

    $this->mock(ProviderRepository::class, function ($mock) {
        $mock->shouldReceive('exists')->once()->andReturn(true);
    });

    $this->mock(ProviderRedirector::class, function ($mock) {
        $mock->shouldReceive('userAlreadyExists')->once()->andReturn(redirect('/'));
    });

    app(UserProviderHandler::class)->callback($provider, 'foo');
});

it('can handle exception when unable to create or update user', function () {
    $socialite = new SocialiteUser;

    $provider = $this->mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($socialite);

    $this->mock(ProviderRepository::class, function ($mock) {
        $mock->shouldReceive('exists')->once()->andReturn(false);
        $mock->shouldReceive('updateOrCreate')->once()->andThrow(Exception::class);
    });

    $this->mock(ProviderRedirector::class, function ($mock) {
        $mock->shouldReceive('unableToCreateUser')->once()->andReturn(redirect('/'));
    });

    app(UserProviderHandler::class)->callback($provider, 'foo');
});

it('can authenticate user', function () {
    $user = new User;
    $socialite = new SocialiteUser;

    $provider = $this->mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($socialite);

    $this->mock(ProviderRepository::class, function ($mock) use ($user) {
        $mock->shouldReceive('exists')->once()->andReturn(false);
        $mock->shouldReceive('updateOrCreate')->once()->andReturn($user);
    });

    $this->mock(ProviderRedirector::class, function ($mock) {
        $mock->shouldReceive('userAuthenticated')->once()->andReturn(redirect('/'));
    });

    app(UserProviderHandler::class)->callback($provider, 'foo');
});

it('handles a missing provider ID as an authentication failure', function ($id, bool $existing) {
    Bartender::setUserModel(User::class);

    if ($existing) {
        User::create([
            'provider_name' => 'foo', 'provider_id' => $id,
            'name' => 'Original', 'email' => 'original@example.com', 'password' => 'password',
            'provider_access_token' => 'original-token',
        ]);
    }

    $identity = (new SocialiteUser)->map([
        'id' => $id, 'name' => 'Incoming', 'email' => 'unused@example.com',
    ]);
    $identity->token = 'incoming-token';
    $provider = mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($identity);
    $redirector = mock(ProviderRedirector::class);
    $redirector->shouldReceive('unableToAuthenticateUser')->once()
        ->with(Mockery::on(fn ($exception) => $exception instanceof AuthenticationException
            && $exception->getMessage() === 'The provider did not return a user ID.'), 'foo')
        ->andReturn($redirect = redirect('/invalid-provider'));
    $redirector->shouldNotReceive('userAlreadyExists');
    $redirector->shouldNotReceive('unableToCreateUser');
    $redirector->shouldNotReceive('userAuthenticated');
    app()->instance(ProviderRedirector::class, $redirector);

    expect(app(UserProviderHandler::class)->callback($provider, 'foo'))->toBe($redirect)
        ->and(Auth::check())->toBeFalse()
        ->and(User::count())->toBe($existing ? 1 : 0);

    if ($existing) {
        expect(User::first()->email)->toBe('original@example.com')
            ->and(User::first()->name)->toBe('Original')
            ->and(User::first()->provider_access_token)->toBe('original-token');
    }
})->with([null, '', '   '])->with([true, false]);

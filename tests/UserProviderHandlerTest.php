<?php

use DirectoryTree\Bartender\ProviderRedirector;
use DirectoryTree\Bartender\ProviderRepository;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderHandler;
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

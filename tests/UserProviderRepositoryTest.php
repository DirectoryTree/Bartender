<?php

use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(fn () => Bartender::setUserModel(User::class));

it('determines if user already exists with a different provider', function () {
    User::create([
        'provider_id' => '1',
        'provider_name' => 'foo',
        'name' => 'foo',
        'email' => 'foo@email.com',
        'password' => Hash::make('password'),
    ]);

    $socialite = tap(new SocialiteUser, function ($user) {
        $user->id = '1';
        $user->email = 'foo@email.com';
    });

    expect((new UserProviderRepository)->exists('bar', $socialite))->toBeTrue();
});

it('determines if user already exists with no provider', function () {
    User::create([
        'provider_id' => null,
        'provider_name' => null,
        'name' => 'foo',
        'email' => 'foo@email.com',
        'password' => Hash::make('password'),
    ]);

    $socialite = tap(new SocialiteUser, function ($user) {
        $user->id = '1';
        $user->email = 'foo@email.com';
    });

    expect((new UserProviderRepository)->exists('bar', $socialite))->toBeTrue();
});

it('creates new user', function () {
    $socialite = tap(new SocialiteUser, function (SocialiteUser $user) {
        $user->id = '1';
        $user->name = 'foo';
        $user->email = 'foo@email.com';
        $user->token = '1234';
        $user->refreshToken = '2345';
    });

    $user = (new UserProviderRepository)->updateOrCreate('foo', $socialite);

    expect($user->wasRecentlyCreated)->toBeTrue();
    expect($user->name)->toBe('foo');
    expect($user->email)->toBe('foo@email.com');
    expect($user->provider_id)->toBe('1');
    expect($user->provider_name)->toBe('foo');
    expect($user->provider_access_token)->toBe('1234');
    expect($user->provider_refresh_token)->toBe('2345');
});

it('updates user not associated to provider', function () {
    User::create([
        'provider_id' => '1',
        'provider_name' => 'foo',
        'name' => 'bar',
        'email' => 'foo@email.com',
        'password' => 'password',
    ]);

    $socialite = tap(new SocialiteUser, function (SocialiteUser $user) {
        $user->id = '1';
        $user->name = 'foo';
        $user->email = 'foo@email.com';
    });

    $user = (new UserProviderRepository)->updateOrCreate('foo', $socialite);

    expect($user->wasRecentlyCreated)->toBeFalse();
    expect($user->name)->toBe('foo');
    expect($user->email)->toBe('foo@email.com');
    expect($user->provider_id)->toBe('1');
    expect($user->provider_name)->toBe('foo');
    expect($user->provider_access_token)->toBeNull();
    expect($user->provider_refresh_token)->toBeNull();
});

it('throws exception when attempting to create existing user with null provider', function () {
    User::create([
        'name' => 'bar',
        'email' => 'foo@email.com',
        'password' => 'password',
    ]);

    $this->expectException(QueryException::class);

    $socialite = tap(new SocialiteUser, function (SocialiteUser $user) {
        $user->id = '1';
        $user->name = 'foo';
        $user->email = 'foo@email.com';
    });

    (new UserProviderRepository)->updateOrCreate('foo', $socialite);
});

it('throws exception when attempting to create existing user with another provider', function () {
    User::create([
        'name' => 'bar',
        'provider_id' => '456',
        'provider_name' => 'foo',
        'email' => 'foo@email.com',
        'password' => 'password',
    ]);

    $socialite = tap(new SocialiteUser, function (SocialiteUser $user) {
        $user->id = '123';
        $user->name = 'bar';
        $user->email = 'foo@email.com';
    });

    $this->expectException(QueryException::class);

    (new UserProviderRepository)->updateOrCreate('bar', $socialite);
});

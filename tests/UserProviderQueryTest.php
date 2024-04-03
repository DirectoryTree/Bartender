<?php

use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderQuery;
use Laravel\Socialite\Two\User as SocialiteUser;

it('determines if user exists but by different provider', function () {
    Bartender::useUserModel(User::class);

    $socialite = tap(new SocialiteUser(), function ($user) {
        $user->id = '1';
        $user->email = 'foo@email.com';
    });

    User::create([
        'provider_id' => '1',
        'provider_name' => 'foo',
        'name' => 'foo',
        'email' => 'foo@email.com',
        'password' => bcrypt('password'),
    ]);

    expect((new UserProviderQuery)->exists('bar', $socialite))->toBeTrue();
});

it('determines if user exists but by no provider', function () {
    Bartender::useUserModel(User::class);

    $socialite = tap(new SocialiteUser(), function ($user) {
        $user->id = '1';
        $user->email = 'foo@email.com';
    });

    User::create([
        'provider_id' => null,
        'provider_name' => null,
        'name' => 'foo',
        'email' => 'foo@email.com',
        'password' => bcrypt('password'),
    ]);

    expect((new UserProviderQuery)->exists('bar', $socialite))->toBeTrue();
});

it('creates new user', function () {
    Bartender::useUserModel(User::class);

    $socialite = tap(new SocialiteUser(), function (SocialiteUser $user) {
        $user->id = '1';
        $user->name = 'foo';
        $user->email = 'foo@email.com';
    });

    $user = (new UserProviderQuery)->updateOrCreate('foo', $socialite);

    expect($user->name)->toBe('foo');
    expect($user->email)->toBe('foo@email.com');
    expect($user->provider_id)->toBe('1');
    expect($user->provider_name)->toBe('foo');
});

it('updates user', function () {
    Bartender::useUserModel(User::class);

    $socialite = tap(new SocialiteUser(), function (SocialiteUser $user) {
        $user->id = '1';
        $user->name = 'foo';
        $user->email = 'foo@email.com';
    });

    User::create([
        'provider_id' => '1',
        'provider_name' => 'foo',
        'name' => 'bar',
        'email' => 'bar@email.com',
        'password' => 'password',
    ]);

    $user = (new UserProviderQuery)->updateOrCreate('foo', $socialite);

    expect($user->wasRecentlyCreated)->toBeFalse();
    expect($user->name)->toBe('foo');
    expect($user->email)->toBe('foo@email.com');
    expect($user->provider_id)->toBe('1');
    expect($user->provider_name)->toBe('foo');
});

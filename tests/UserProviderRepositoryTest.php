<?php

use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderRepository;
use Illuminate\Auth\AuthenticationException;
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


    $socialite = tap(new SocialiteUser, function (SocialiteUser $user) {
        $user->id = '1';
        $user->name = 'foo';
        $user->email = 'foo@email.com';
    });

    expect(fn () => (new UserProviderRepository)->updateOrCreate('foo', $socialite))->toThrow(AuthenticationException::class);
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


    expect(fn () => (new UserProviderRepository)->updateOrCreate('bar', $socialite))->toThrow(AuthenticationException::class);
});


it('keeps the same account when its provider email changes', function () {
    $account = User::create([
        'provider_name' => 'foo', 'provider_id' => '123', 'name' => 'Before',
        'email' => 'old@example.com', 'password' => 'password',
        'provider_refresh_token' => 'refresh-token',
    ]);
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'After', 'email' => 'new@example.com',
    ]);
    $identity->token = 'new-token';

    $result = (new UserProviderRepository)->updateOrCreate('foo', $identity);

    expect($result->id)->toBe($account->id)
        ->and($account->fresh()->email)->toBe('new@example.com')
        ->and($account->fresh()->provider_refresh_token)->toBe('refresh-token')
        ->and(User::count())->toBe(1);
});

it('rejects a different identity with an existing email without changing the account', function (string $driver) {
    $account = User::create([
        'provider_name' => 'foo', 'provider_id' => 'original', 'name' => 'Original',
        'email' => 'person@example.com', 'password' => 'password',
        'provider_access_token' => 'original-token',
    ]);
    $identity = (new SocialiteUser)->map([
        'id' => 'different', 'name' => 'Different', 'email' => 'person@example.com',
    ]);

    $repository = new UserProviderRepository;
    expect($repository->exists($driver, $identity))->toBeTrue();
    expect(fn () => $repository->updateOrCreate($driver, $identity))->toThrow(AuthenticationException::class);
    expect($account->fresh()->provider_id)->toBe('original')
        ->and($account->fresh()->provider_access_token)->toBe('original-token')
        ->and(User::count())->toBe(1);
})->with(['foo', 'bar']);

it('rejects a changed provider email that belongs to another account', function () {
    $account = User::create([
        'provider_name' => 'foo', 'provider_id' => '123', 'name' => 'Original',
        'email' => 'original@example.com', 'password' => 'password',
    ]);
    $other = User::create([
        'provider_name' => 'foo', 'provider_id' => '456', 'name' => 'Other',
        'email' => 'other@example.com', 'password' => 'password',
    ]);
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Updated', 'email' => 'other@example.com',
    ]);

    expect(fn () => (new UserProviderRepository)->updateOrCreate('foo', $identity))
        ->toThrow(AuthenticationException::class);
    expect($account->fresh()->email)->toBe('original@example.com')
        ->and($other->fresh()->provider_id)->toBe('456');
});

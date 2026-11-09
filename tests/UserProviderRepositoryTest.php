<?php

use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\Tests\EnumProviderIdUser;
use DirectoryTree\Bartender\Tests\EnumUser;
use DirectoryTree\Bartender\Tests\IntegerProviderIdUser;
use DirectoryTree\Bartender\Tests\ProviderId;
use DirectoryTree\Bartender\Tests\ProviderName;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderRepository;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\test;

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

it('rejects missing provider IDs without modifying or creating accounts', function ($id, bool $existing) {
    if ($existing) {
        User::create([
            'provider_name' => 'foo', 'provider_id' => $id, 'name' => 'Original',
            'email' => 'original@example.com', 'password' => 'password',
            'provider_access_token' => 'original-token',
        ]);
    }

    $identity = (new SocialiteUser)->map([
        'id' => $id, 'name' => 'Incoming', 'email' => 'unused@example.com',
    ]);
    $identity->token = 'incoming-token';
    $repository = new UserProviderRepository;

    expect(fn () => $repository->exists('foo', $identity))
        ->toThrow(AuthenticationException::class, 'The provider did not return a user ID.');
    expect(fn () => $repository->updateOrCreate('foo', $identity))
        ->toThrow(AuthenticationException::class, 'The provider did not return a user ID.');
    expect(User::count())->toBe($existing ? 1 : 0);

    if ($existing) {
        expect(User::first()->email)->toBe('original@example.com')
            ->and(User::first()->name)->toBe('Original')
            ->and(User::first()->provider_access_token)->toBe('original-token');
    }
})->with([null, '', '   '])->with([true, false]);

it('accepts zero as a provider ID', function ($id) {
    $identity = (new SocialiteUser)->map([
        'id' => $id, 'name' => 'Zero', 'email' => 'zero@example.com',
    ]);
    $repository = new UserProviderRepository;

    expect($repository->exists('foo', $identity))->toBeFalse();
    $created = $repository->updateOrCreate('foo', $identity);
    $updated = $repository->updateOrCreate('foo', $identity);

    expect((string) $created->provider_id)->toBe('0')
        ->and($updated->id)->toBe($created->id)
        ->and(User::count())->toBe(1);
})->with([0, '0']);

it('rejects inexact identities on case insensitive columns', function (string $storedDriver, string $driver, string $storedId, string $id, bool $sameEmail) {
    if (DB::getDriverName() !== 'sqlite') {
        test()->markTestSkipped('This regression fixture requires SQLite NOCASE columns.');
    }

    Schema::drop('users');
    DB::statement('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider_name TEXT COLLATE NOCASE,
        provider_id TEXT COLLATE NOCASE,
        name TEXT, email TEXT UNIQUE, password TEXT,
        provider_access_token TEXT, provider_refresh_token TEXT,
        email_verified_at TEXT, created_at TEXT, updated_at TEXT
    )');
    DB::statement('CREATE UNIQUE INDEX users_provider_id_provider_name_unique ON users (provider_id, provider_name)');

    $account = User::create([
        'provider_name' => $storedDriver, 'provider_id' => $storedId,
        'name' => 'Original', 'email' => 'original@example.com', 'password' => 'password',
        'provider_access_token' => 'original-token', 'provider_refresh_token' => 'original-refresh',
    ]);
    $identity = (new SocialiteUser)->map([
        'id' => $id, 'name' => 'Incoming',
        'email' => $sameEmail ? 'original@example.com' : 'unused@example.com',
    ]);
    $identity->token = 'incoming-token';
    $identity->refreshToken = 'incoming-refresh';
    $repository = new UserProviderRepository;

    expect($repository->exists($driver, $identity))->toBe($sameEmail);
    expect(fn () => $repository->updateOrCreate($driver, $identity))
        ->toThrow(AuthenticationException::class);
    expect($account->fresh()->provider_id)->toBe($storedId)
        ->and($account->fresh()->provider_name)->toBe($storedDriver)
        ->and($account->fresh()->name)->toBe('Original')
        ->and($account->fresh()->email)->toBe('original@example.com')
        ->and($account->fresh()->provider_access_token)->toBe('original-token')
        ->and($account->fresh()->provider_refresh_token)->toBe('original-refresh')
        ->and(User::count())->toBe(1);
})->with([
    'provider ID differs in case' => ['foo', 'foo', 'ABC', 'abc'],
    'provider name differs in case' => ['FOO', 'foo', '123', '123'],
])->with([true, false]);

it('updates exact identities on case insensitive columns', function () {
    if (DB::getDriverName() !== 'sqlite') {
        test()->markTestSkipped('This regression fixture requires SQLite NOCASE columns.');
    }

    Schema::drop('users');
    DB::statement('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider_name TEXT COLLATE NOCASE,
        provider_id TEXT COLLATE NOCASE,
        name TEXT, email TEXT UNIQUE, password TEXT,
        provider_access_token TEXT, provider_refresh_token TEXT,
        email_verified_at TEXT, created_at TEXT, updated_at TEXT
    )');
    DB::statement('CREATE UNIQUE INDEX users_provider_id_provider_name_unique ON users (provider_id, provider_name)');

    $account = User::create([
        'provider_name' => 'foo', 'provider_id' => 'ABC',
        'name' => 'Original', 'email' => 'original@example.com', 'password' => 'password',
        'provider_refresh_token' => 'original-refresh',
    ]);
    $identity = (new SocialiteUser)->map([
        'id' => 'ABC', 'name' => 'Updated', 'email' => 'updated@example.com',
    ]);
    $identity->token = 'updated-token';
    $repository = new UserProviderRepository;

    expect($repository->exists('foo', $identity))->toBeFalse();
    expect($repository->updateOrCreate('foo', $identity)->id)->toBe($account->id);
    expect($repository->exists('foo', $identity))->toBeFalse();
    expect($account->fresh()->email)->toBe('updated@example.com')
        ->and($account->fresh()->provider_access_token)->toBe('updated-token')
        ->and($account->fresh()->provider_refresh_token)->toBe('original-refresh')
        ->and(User::count())->toBe(1);
});

it('uses Socialite accessors when public profile properties differ', function () {
    $account = User::create([
        'provider_name' => 'foo', 'provider_id' => '123',
        'name' => 'Original', 'email' => 'original@example.com', 'password' => 'password',
    ]);
    $identity = new class extends SocialiteUser
    {
        public function getId()
        {
            return '123';
        }

        public function getEmail()
        {
            return 'updated@example.com';
        }

        public function getName()
        {
            return 'Updated Name';
        }
    };
    $identity->id = 'stale-id';
    $identity->email = 'stale@example.com';
    $identity->name = 'Stale Name';

    $result = (new UserProviderRepository)->updateOrCreate('foo', $identity);

    expect($result->id)->toBe($account->id)
        ->and($result->provider_id)->toBe('123')
        ->and($result->email)->toBe('updated@example.com')
        ->and($result->name)->toBe('Updated Name')
        ->and(User::count())->toBe(1);
});

it('updates the same identity when the provider name is enum cast', function (string $email) {
    Bartender::setUserModel(EnumUser::class);

    $account = EnumUser::create([
        'provider_name' => ProviderName::Foo,
        'provider_id' => '123',
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => 'password',
        'provider_refresh_token' => 'original-refresh',
    ]);

    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Updated', 'email' => $email,
    ]);
    $identity->token = 'updated-token';

    $repository = new UserProviderRepository;

    expect($repository->exists('foo', $identity))->toBeFalse();

    $result = $repository->updateOrCreate('foo', $identity);

    expect($result->id)->toBe($account->id)
        ->and($result->provider_name)->toBe(ProviderName::Foo)
        ->and($account->fresh()->email)->toBe($email)
        ->and($account->fresh()->provider_access_token)->toBe('updated-token')
        ->and($account->fresh()->provider_refresh_token)->toBe('original-refresh')
        ->and(EnumUser::count())->toBe(1);
})->with(['original@example.com', 'changed@example.com']);

it('rejects conflicting identities when the provider name is enum cast', function (string $driver, string $id) {
    Bartender::setUserModel(EnumUser::class);

    $account = EnumUser::create([
        'provider_name' => ProviderName::Foo,
        'provider_id' => '123',
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => 'password',
        'provider_access_token' => 'original-token',
    ]);

    $identity = (new SocialiteUser)->map([
        'id' => $id, 'name' => 'Incoming', 'email' => 'original@example.com',
    ]);
    $identity->token = 'incoming-token';

    $repository = new UserProviderRepository;

    expect($repository->exists($driver, $identity))->toBeTrue();
    expect(fn () => $repository->updateOrCreate($driver, $identity))->toThrow(AuthenticationException::class);
    expect($account->fresh()->provider_name)->toBe(ProviderName::Foo)
        ->and($account->fresh()->provider_id)->toBe('123')
        ->and($account->fresh()->provider_access_token)->toBe('original-token')
        ->and(EnumUser::count())->toBe(1);
})->with([
    'different provider' => ['bar', '123'],
    'different provider ID' => ['foo', '456'],
]);

it('updates exact identities without applying provider ID casts to comparisons', function (string $model, $castId, string $email) {
    Bartender::setUserModel($model);

    $account = $model::create([
        'provider_name' => 'foo',
        'provider_id' => '00123',
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => 'password',
        'provider_refresh_token' => 'original-refresh',
    ]);

    $identity = (new SocialiteUser)->map([
        'id' => '00123', 'name' => 'Updated', 'email' => $email,
    ]);
    $identity->token = 'updated-token';

    $repository = new UserProviderRepository;

    expect($repository->exists('foo', $identity))->toBeFalse();

    $result = $repository->updateOrCreate('foo', $identity);

    expect($result->id)->toBe($account->id)
        ->and($result->provider_id)->toBe($castId)
        ->and($account->fresh()->getRawOriginal('provider_id'))->toBe('00123')
        ->and($account->fresh()->email)->toBe($email)
        ->and($account->fresh()->provider_access_token)->toBe('updated-token')
        ->and($account->fresh()->provider_refresh_token)->toBe('original-refresh')
        ->and($model::count())->toBe(1);
})->with([
    'enum cast' => [EnumProviderIdUser::class, ProviderId::Original],
    'integer cast' => [IntegerProviderIdUser::class, 123],
])->with(['original@example.com', 'changed@example.com']);

it('rejects conflicting identities without applying provider ID casts to comparisons', function (string $model, string $id) {
    Bartender::setUserModel($model);

    $account = $model::create([
        'provider_name' => 'foo',
        'provider_id' => '00123',
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => 'password',
        'provider_access_token' => 'original-token',
    ]);

    $identity = (new SocialiteUser)->map([
        'id' => $id, 'name' => 'Incoming', 'email' => 'original@example.com',
    ]);
    $identity->token = 'incoming-token';

    $repository = new UserProviderRepository;

    expect($repository->exists('foo', $identity))->toBeTrue();
    expect(fn () => $repository->updateOrCreate('foo', $identity))->toThrow(AuthenticationException::class);
    expect($account->fresh()->getRawOriginal('provider_id'))->toBe('00123')
        ->and($account->fresh()->name)->toBe('Original')
        ->and($account->fresh()->provider_access_token)->toBe('original-token')
        ->and($model::count())->toBe(1);
})->with([
    'different enum ID' => [EnumProviderIdUser::class, '00456'],
    'integer cast conceals leading zeros' => [IntegerProviderIdUser::class, '123'],
]);

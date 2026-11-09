<?php

use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\ProviderRedirector;
use DirectoryTree\Bartender\Tests\SoftDeletingUser;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderHandler;
use DirectoryTree\Bartender\UserProviderRepository;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    Schema::table('users', function (Blueprint $table) {
        $table->softDeletes();
    });
    Bartender::setUserModel(SoftDeletingUser::class);
    Bartender::restoreSoftDeletedUsers();
});

it('preserves automatic restoration by default', function (string $email) {
    $account = SoftDeletingUser::create([
        'name' => 'Person', 'email' => 'person@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
    ]);
    $account->delete();
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Person', 'email' => $email,
    ]);

    $restored = (new UserProviderRepository)->updateOrCreate('foo', $identity);

    expect($restored->id)->toBe($account->id)->and($restored->trashed())->toBeFalse();
})->with(['person@example.com', 'changed@example.com']);

it('rejects a deleted account even when its email or provider identity changes', function (string $email, string $id) {
    $account = SoftDeletingUser::create([
        'name' => 'Person', 'email' => 'person@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
    ]);
    $account->delete();
    Bartender::restoreSoftDeletedUsers(false);
    $identity = (new SocialiteUser)->map(['id' => $id, 'name' => 'Person', 'email' => $email]);
    $repository = new UserProviderRepository;

    expect(fn () => $repository->exists('foo', $identity))
        ->toThrow(AuthenticationException::class, 'This account has been deleted and cannot be restored.');
    expect(fn () => $repository->updateOrCreate('foo', $identity))->toThrow(AuthenticationException::class);
    expect($account->fresh()->trashed())->toBeTrue()
        ->and(SoftDeletingUser::withTrashed()->count())->toBe(1);
})->with([
    ['person@example.com', '123'],
    ['changed@example.com', '123'],
    ['person@example.com', 'different'],
]);

it('allows active accounts and models without soft deletes when restoration is disabled', function () {
    Bartender::restoreSoftDeletedUsers(false);
    $account = SoftDeletingUser::create([
        'name' => 'Person', 'email' => 'person@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
    ]);
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Person', 'email' => 'person@example.com',
    ]);

    expect((new UserProviderRepository)->updateOrCreate('foo', $identity)->id)->toBe($account->id);
    Bartender::setUserModel(User::class);
    expect((new UserProviderRepository)->updateOrCreate('foo', $identity)->id)->toBe($account->id);
});

it('allows an active identity to change email without matching unrelated deleted accounts', function (string $deletedDriver, string $deletedId) {
    Bartender::restoreSoftDeletedUsers(false);
    $account = SoftDeletingUser::create([
        'name' => 'Active', 'email' => 'active@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
    ]);
    $deleted = SoftDeletingUser::create([
        'name' => 'Deleted', 'email' => 'deleted@example.com', 'password' => 'password',
        'provider_name' => $deletedDriver, 'provider_id' => $deletedId,
    ]);
    $deleted->delete();
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Updated', 'email' => 'changed@example.com',
    ]);
    $repository = new UserProviderRepository;

    expect($repository->exists('foo', $identity))->toBeFalse();
    expect($repository->updateOrCreate('foo', $identity)->id)->toBe($account->id);
    expect($account->fresh()->email)->toBe('changed@example.com')
        ->and($deleted->fresh()->trashed())->toBeTrue()
        ->and($deleted->fresh()->email)->toBe('deleted@example.com')
        ->and(SoftDeletingUser::withTrashed()->count())->toBe(2);
})->with([
    'same provider, different ID' => ['foo', '456'],
    'different provider, same ID' => ['bar', '123'],
]);

it('rejects email conflicts without restoring the matching deleted identity', function (bool $restore) {
    Bartender::restoreSoftDeletedUsers($restore);
    $active = SoftDeletingUser::create([
        'name' => 'Active', 'email' => 'taken@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '456',
    ]);
    $deleted = SoftDeletingUser::create([
        'name' => 'Deleted', 'email' => 'old@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
        'provider_access_token' => 'original-token',
    ]);
    $deleted->delete();
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Incoming', 'email' => 'taken@example.com',
    ]);
    $identity->token = 'incoming-token';
    $repository = new UserProviderRepository;

    if ($restore) {
        expect($repository->exists('foo', $identity))->toBeTrue();
    } else {
        expect(fn () => $repository->exists('foo', $identity))
            ->toThrow(AuthenticationException::class, 'This account has been deleted and cannot be restored.');
    }
    expect(fn () => $repository->updateOrCreate('foo', $identity))
        ->toThrow(AuthenticationException::class);
    expect($deleted->fresh()->trashed())->toBeTrue()
        ->and($deleted->fresh()->email)->toBe('old@example.com')
        ->and($deleted->fresh()->provider_access_token)->toBe('original-token')
        ->and($active->fresh()->email)->toBe('taken@example.com')
        ->and($active->fresh()->provider_id)->toBe('456');
})->with([true, false]);

it('respects a custom soft delete column when matching changed emails', function () {
    Schema::table('users', function (Blueprint $table) {
        $table->softDeletes('archived_at');
    });
    $model = new class extends SoftDeletingUser
    {
        public const DELETED_AT = 'archived_at';
    };
    Bartender::setUserModel($model::class);
    Bartender::restoreSoftDeletedUsers(false);
    $account = $model::create([
        'name' => 'Deleted', 'email' => 'old@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
    ]);
    $account->delete();
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Incoming', 'email' => 'changed@example.com',
    ]);
    $repository = new UserProviderRepository;

    expect(fn () => $repository->exists('foo', $identity))
        ->toThrow(AuthenticationException::class, 'This account has been deleted and cannot be restored.');
    expect(fn () => $repository->updateOrCreate('foo', $identity))
        ->toThrow(AuthenticationException::class);
    expect($account->fresh()->trashed())->toBeTrue()
        ->and($account->fresh()->email)->toBe('old@example.com');

    Bartender::restoreSoftDeletedUsers();

    expect($repository->exists('foo', $identity))->toBeFalse();
    expect($repository->updateOrCreate('foo', $identity)->id)->toBe($account->id);
    expect($account->fresh()->trashed())->toBeFalse()
        ->and($account->fresh()->email)->toBe('changed@example.com');
});

it('rechecks deletion when it occurs between eligibility and account lookup', function (bool $restore) {
    Bartender::restoreSoftDeletedUsers($restore);
    $account = SoftDeletingUser::create([
        'name' => 'Original', 'email' => 'original@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
        'provider_access_token' => 'original-token',
    ]);
    $identity = (new SocialiteUser)->map([
        'id' => '123', 'name' => 'Incoming', 'email' => 'changed@example.com',
    ]);
    $identity->token = 'incoming-token';
    $repository = new class($account) extends UserProviderRepository
    {
        public function __construct(
            protected SoftDeletingUser $account,
        ) {}

        public function exists(string $driver, SocialiteUserContract $user): bool
        {
            $exists = parent::exists($driver, $user);
            $this->account->delete();

            return $exists;
        }
    };

    if ($restore) {
        expect($repository->updateOrCreate('foo', $identity)->id)->toBe($account->id);
        expect($account->fresh()->trashed())->toBeFalse()
            ->and($account->fresh()->email)->toBe('changed@example.com');
    } else {
        expect(fn () => $repository->updateOrCreate('foo', $identity))
            ->toThrow(AuthenticationException::class, 'This account has been deleted and cannot be restored.');
        expect($account->fresh()->trashed())->toBeTrue()
            ->and($account->fresh()->name)->toBe('Original')
            ->and($account->fresh()->email)->toBe('original@example.com')
            ->and($account->fresh()->provider_access_token)->toBe('original-token');
    }

    expect(SoftDeletingUser::withTrashed()->count())->toBe(1);
})->with([true, false]);

it('reports deleted identities as authentication failures instead of email conflicts', function (string $email) {
    Bartender::restoreSoftDeletedUsers(false);
    $account = SoftDeletingUser::create([
        'name' => 'Deleted', 'email' => 'old@example.com', 'password' => 'password',
        'provider_name' => 'foo', 'provider_id' => '123',
    ]);
    $account->delete();
    $identity = (new SocialiteUser)->map(['id' => '123', 'name' => 'Incoming', 'email' => $email]);
    $provider = mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($identity);
    $redirector = mock(ProviderRedirector::class);
    $redirector->shouldReceive('unableToAuthenticateUser')->once()
        ->with(Mockery::on(fn ($exception) => $exception instanceof AuthenticationException
            && $exception->getMessage() === 'This account has been deleted and cannot be restored.'), 'foo')
        ->andReturn($redirect = redirect('/deleted-account'));
    $redirector->shouldNotReceive('userAlreadyExists');
    $redirector->shouldNotReceive('userAuthenticated');
    app()->instance(ProviderRedirector::class, $redirector);

    expect(app(UserProviderHandler::class)->callback($provider, 'foo'))->toBe($redirect)
        ->and(Auth::check())->toBeFalse()
        ->and($account->fresh()->trashed())->toBeTrue()
        ->and($account->fresh()->email)->toBe('old@example.com')
        ->and(SoftDeletingUser::withTrashed()->count())->toBe(1);
})->with(['old@example.com', 'changed@example.com']);

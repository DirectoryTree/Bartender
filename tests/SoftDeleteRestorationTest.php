<?php

use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\Tests\SoftDeletingUser;
use DirectoryTree\Bartender\Tests\User;
use DirectoryTree\Bartender\UserProviderRepository;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    expect($repository->exists('foo', $identity))->toBeTrue();
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

<?php

use DirectoryTree\Bartender\BartenderManager;
use DirectoryTree\Bartender\Facades\Bartender;
use DirectoryTree\Bartender\Tests\User;
use Illuminate\Support\Facades\Route;

it('is bound to facade', function () {
    expect(Bartender::getFacadeRoot())->toBeInstanceOf(BartenderManager::class);
});

it('can register handlers', function () {
    $manager = new BartenderManager;

    $manager->serve('foo', stdClass::class);

    expect($manager->handlers())->toBe(['foo' => stdClass::class]);
});

it('returns new user model', function () {
    $manager = new BartenderManager;

    $manager->setUserModel(User::class);

    expect($manager->getUserModel())->toBe(User::class);
});

it('registers routes', function () {
    $manager = new BartenderManager;

    $manager->routes();

    expect(Route::has('auth.driver.callback'))->toBeTrue();
    expect(Route::has('auth.driver.redirect'))->toBeTrue();
});

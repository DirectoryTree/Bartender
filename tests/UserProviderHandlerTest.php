<?php

namespace DirectoryTree\Bartender\Tests;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use DirectoryTree\Bartender\UserProviderHandler;

class UserProviderHandlerTest extends TestCase
{
    public function testItCanRedirectToProvider()
    {
        $provider = mock(Provider::class);

        $provider->shouldReceive('redirect')->andReturn($redirect = redirect('/'));

        $this->assertEquals($redirect, (new UserProviderHandler)->redirect($provider, 'foo'));
    }

    public function testItCanHandleExceptionWhenUserCannotBeAuthenticated()
    {
        $provider = mock(Provider::class);

        $provider->shouldReceive('user')->once()->andThrow(Exception::class);

        $this->assertInstanceOf(RedirectResponse::class, (new UserProviderHandler)->callback($provider, 'foo'));
    }

    public function testItCanHandleWhenUserAlreadyExists()
    {
        $this->artisan('migrate:fresh');
        dd(DB::table('users')->get());
        $provider = mock(Provider::class);

        $socialite = new SocialiteUser();

        DB::table('users')->get();

        User::create();

        $provider->shouldReceive('user')->once()->andReturn($socialite);

        (new UserProviderHandler)->callback($provider, 'foo');

        $provider->shouldReceive('user')->andReturn($socialite = mock());
    }
}
<?php

namespace DirectoryTree\Bartender\Tests;

use DirectoryTree\Bartender\StoresProviderTokens;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements StoresProviderTokens
{
    use MustVerifyEmail;

    protected $guarded = [];
}

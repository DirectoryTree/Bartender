<?php

namespace DirectoryTree\Bartender\Tests;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use MustVerifyEmail;

    protected $guarded = [];
}

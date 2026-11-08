<?php

namespace DirectoryTree\Bartender\Tests;

use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletingUser extends User
{
    use SoftDeletes;

    protected $table = 'users';
}

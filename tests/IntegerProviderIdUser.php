<?php

namespace DirectoryTree\Bartender\Tests;

class IntegerProviderIdUser extends User
{
    protected $table = 'users';

    protected $casts = [
        'provider_id' => 'integer',
    ];
}

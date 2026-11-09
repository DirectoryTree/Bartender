<?php

namespace DirectoryTree\Bartender\Tests;

class EnumUser extends User
{
    protected $table = 'users';

    protected $casts = [
        'provider_name' => ProviderName::class,
    ];
}

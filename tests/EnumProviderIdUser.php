<?php

namespace DirectoryTree\Bartender\Tests;

class EnumProviderIdUser extends User
{
    protected $table = 'users';

    protected $casts = [
        'provider_id' => ProviderId::class,
    ];
}

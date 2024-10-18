<?php

namespace DirectoryTree\Bartender\Events;

use Illuminate\Contracts\Auth\Authenticatable;

class UserAuthenticated
{
    /**
     * Constructor.
     */
    public function __construct(
        public Authenticatable $user
    ) {}
}

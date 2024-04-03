<?php

namespace DirectoryTree\Bartender\Facades;

use Illuminate\Support\Facades\Facade;
use DirectoryTree\Bartender\BartenderManager;

/**
 * @method static void routes()
 * @method static void useUserModel(string $userModel)
 * @method static \Illuminate\Database\Eloquent\Model user()
 * @method static void register(string $driver, string $handler)
 * @method static \Illuminate\Http\RedirectResponse redirect(string $driver)
 * @method static \Illuminate\Http\RedirectResponse callback(string $driver)
 */
class Bartender extends Facade
{
    /**
     * The facade accessor string.
     */
    protected static function getFacadeAccessor(): string
    {
        return BartenderManager::class;
    }
}

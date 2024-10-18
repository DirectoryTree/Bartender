<?php

namespace DirectoryTree\Bartender\Facades;

use DirectoryTree\Bartender\BartenderManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void routes()
 * @method static array handlers()
 * @method static string getUserModel()
 * @method static void setUserModel(string $userModel)
 * @method static void serve(string $driver, ?string $handler = null)
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

<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void put(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static void flash(string $key, mixed $value)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static void regenerate()
 */
class Session extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Session\SessionManager::class;
    }
}

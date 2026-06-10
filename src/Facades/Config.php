<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static array all()
 */
class Config extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Config\Repository::class;
    }
}

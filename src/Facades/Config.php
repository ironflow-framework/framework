<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

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
        return \Ironflow\Config\Repository::class;
    }
}

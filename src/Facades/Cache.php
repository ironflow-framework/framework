<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void put(string $key, mixed $value, int $ttl = 3600)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static void flush()
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Cache\CacheManager::class;
    }
}

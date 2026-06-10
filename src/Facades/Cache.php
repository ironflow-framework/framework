<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

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
        return \Ironflow\Cache\CacheManager::class;
    }
}

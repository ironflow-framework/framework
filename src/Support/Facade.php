<?php

declare(strict_types=1);

namespace Ironflow\Support;

use Ironflow\Container;

/**
 * Abstract Facade. Each concrete facade returns an accessor string (class name
 * or binding key) that is resolved from the Container on __callStatic.
 */
abstract class Facade
{
    private static Container $container;

    /** @var array<string, object> Per-facade resolved instance cache. */
    private static array $resolved = [];

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
        self::$resolved = [];
    }

    /** Return the Container binding key (usually a FQCN). */
    abstract protected static function getFacadeAccessor(): string;

    public static function __callStatic(string $method, array $args): mixed
    {
        return static::getFacadeRoot()->$method(...$args);
    }

    protected static function getFacadeRoot(): object
    {
        $accessor = static::getFacadeAccessor();

        if (!isset(self::$resolved[$accessor])) {
            self::$resolved[$accessor] = self::$container->make($accessor);
        }

        return self::$resolved[$accessor];
    }

    /** Clear resolved instance (useful in tests). */
    public static function clearResolvedInstance(): void
    {
        $accessor = static::getFacadeAccessor();
        unset(self::$resolved[$accessor]);
    }
}

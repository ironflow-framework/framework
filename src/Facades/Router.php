<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static \Ironflow\Routing\Route get(string $uri, array|callable $action)
 * @method static \Ironflow\Routing\Route post(string $uri, array|callable $action)
 * @method static \Ironflow\Routing\Route put(string $uri, array|callable $action)
 * @method static \Ironflow\Routing\Route patch(string $uri, array|callable $action)
 * @method static \Ironflow\Routing\Route delete(string $uri, array|callable $action)
 * @method static void group(array $attributes, callable $callback)
 * @method static void resource(string $name, string $controller)
 * @method static string route(string $name, array $params = [])
 */
class Router extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Routing\Router::class;
    }
}

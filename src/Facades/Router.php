<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static \Core\Routing\Route get(string $uri, array|callable $action)
 * @method static \Core\Routing\Route post(string $uri, array|callable $action)
 * @method static \Core\Routing\Route put(string $uri, array|callable $action)
 * @method static \Core\Routing\Route patch(string $uri, array|callable $action)
 * @method static \Core\Routing\Route delete(string $uri, array|callable $action)
 * @method static void group(array $attributes, callable $callback)
 * @method static void resource(string $name, string $controller)
 * @method static string route(string $name, array $params = [])
 */
class Router extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Routing\Router::class;
    }
}

<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static string render(string $template, array $data = [])
 * @method static void composer(string $template, callable $callback)
 * @method static void addNamespace(string $namespace, string $path)
 */
class View extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Template\Engine::class;
    }
}

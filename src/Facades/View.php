<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static string render(string $template, array $data = [])
 * @method static void composer(string $template, callable $callback)
 * @method static void addNamespace(string $namespace, string $path)
 */
class View extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Template\Engine::class;
    }
}

<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static void debug(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 */
class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Logging\Logger::class;
    }
}

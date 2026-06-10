<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static \Core\Database\QueryBuilder table(string $table)
 * @method static mixed select(string $sql, array $bindings = [])
 * @method static int statement(string $sql, array $bindings = [])
 * @method static void transaction(callable $callback)
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Database\Connection::class;
    }
}

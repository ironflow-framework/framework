<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static \Ironflow\Database\QueryBuilder table(string $table)
 * @method static mixed select(string $sql, array $bindings = [])
 * @method static int statement(string $sql, array $bindings = [])
 * @method static void transaction(callable $callback)
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Database\Connection::class;
    }
}

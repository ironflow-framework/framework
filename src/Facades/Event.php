<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static void dispatch(object $event)
 * @method static void listen(string $eventClass, callable|string $listener)
 * @method static void subscribe(object $subscriber)
 */
class Event extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Events\Dispatcher::class;
    }
}

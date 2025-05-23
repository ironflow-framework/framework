<?php

namespace IronFlow\Support\Facades;

use IronFlow\Support\Facades\Facade;
use IronFlow\Channel\Providers\WebSocketProvider;

class Channel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebSocketProvider::class;
    }

    protected static function getFacadeInstance(): object
    {
        return WebSocketProvider::getInstance();
    }
}


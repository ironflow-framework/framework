<?php

declare(strict_types=1);

namespace Core\Facades;

use Core\Support\Facade;

/**
 * @method static \Core\Validation\ValidatorInstance make(array $data, array $rules, array $messages = [])
 */
class Validator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Validation\ValidatorFactory::class;
    }
}

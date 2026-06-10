<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static \Ironflow\Validation\ValidatorInstance make(array $data, array $rules, array $messages = [])
 */
class Validator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Validation\ValidatorFactory::class;
    }
}

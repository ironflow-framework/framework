<?php

declare(strict_types=1);

namespace Core\Validation;

use Core\Application;
use Core\Database\Connection;

/**
 * Factory to create ValidatorInstance objects.
 * Injected via the container; exposes make() matching the Validator facade API.
 */
class ValidatorFactory
{
    public function make(array $data, array $rules, array $messages = []): ValidatorInstance
    {
        $db = null;
        try {
            $db = Application::getInstance()->getContainer()->make(Connection::class);
        } catch (\Throwable) {}

        return new ValidatorInstance($data, $rules, $messages, $db);
    }
}

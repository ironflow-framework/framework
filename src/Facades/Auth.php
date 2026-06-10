<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static bool check()
 * @method static mixed user()
 * @method static bool attempt(array $credentials)
 * @method static void login(object $user)
 * @method static void logout()
 * @method static string createToken(object $user)
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Auth\AuthManager::class;
    }
}

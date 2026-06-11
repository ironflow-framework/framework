<?php

declare(strict_types=1);

namespace Ironflow\Support\Facades;

use Ironflow\Support\Facade;

/**
 * Gate facade — static proxy for the authorization Gate.
 *
 * @method static bool   allows(string $ability, mixed $arguments = [])
 * @method static bool   denies(string $ability, mixed $arguments = [])
 * @method static bool   any(array $abilities, mixed $arguments = [])
 * @method static bool   none(array $abilities, mixed $arguments = [])
 * @method static void   authorize(string $ability, mixed $arguments = [])
 * @method static static define(string $ability, callable $callback)
 * @method static static policy(string $model, string $policyClass)
 * @method static static before(callable $callback)
 * @method static static after(callable $callback)
 * @method static static forUser(?object $user)
 * @method static object|null getPolicyFor(string|object $model)
 *
 * @see \Ironflow\Auth\Gate
 */
class Gate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Auth\Gate::class;
    }
}

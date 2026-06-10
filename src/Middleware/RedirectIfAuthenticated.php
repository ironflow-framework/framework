<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Auth\AuthManager;
use Ironflow\Http\Request;
use Ironflow\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next, string $guard = 'session'): Response
    {
        if ($this->auth->guard($guard)->check()) {
            return new RedirectResponse('/');
        }
        return $next($request);
    }
}

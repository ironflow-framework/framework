<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Auth\AuthManager;
use Ironflow\Exceptions\HttpException;
use Ironflow\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next, string $guard = 'session'): Response
    {
        if (!$this->auth->guard($guard)->check()) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Unauthenticated.');
            }
            throw new HttpException(302, '');
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken
{
    /** URIs excluded from CSRF verification (e.g., API routes) */
    protected array $except = [];

    public function __construct(private readonly SessionManager $session) {}

    public function handle(Request $request, callable $next): Response
    {
        if ($this->isReading($request) || $this->inExceptArray($request) || $this->tokensMatch($request)) {
            return $next($request);
        }

        throw new HttpException(419, 'CSRF token mismatch.');
    }

    private function isReading(Request $request): bool
    {
        return in_array($request->getMethod(), ['HEAD', 'GET', 'OPTIONS'], true);
    }

    private function inExceptArray(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if (fnmatch($pattern, $request->getPathInfo())) {
                return true;
            }
        }
        return false;
    }

    private function tokensMatch(Request $request): bool
    {
        $token = $request->request->get('_token')
            ?? $request->headers->get('X-CSRF-TOKEN')
            ?? $request->headers->get('X-XSRF-TOKEN');

        $sessionToken = $this->session->csrfToken();

        return $token !== null && hash_equals($sessionToken, (string) $token);
    }
}

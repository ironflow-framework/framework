<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds common security headers to every response.
 *
 * Configurable via config/middleware.php → 'security_headers':
 *   [
 *     'X-Frame-Options'           => 'SAMEORIGIN',
 *     'X-Content-Type-Options'    => 'nosniff',
 *     'X-XSS-Protection'          => '1; mode=block',
 *     'Referrer-Policy'           => 'strict-origin-when-cross-origin',
 *     'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
 *   ]
 */
class SecurityHeaders
{
    /** Default headers applied when no config override is present. */
    private const DEFAULTS = [
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection'       => '1; mode=block',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
    ];

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $headers = $this->resolveHeaders();
        foreach ($headers as $name => $value) {
            if ($value === false || $value === null) {
                // Allow explicit opt-out via config
                continue;
            }
            $response->headers->set($name, (string) $value);
        }

        return $response;
    }

    private function resolveHeaders(): array
    {
        try {
            $config = \Ironflow\Application::getInstance()
                ->getContainer()
                ->make(\Ironflow\Config\Repository::class);

            $overrides = $config->get('middleware.security_headers', []);
            if (is_array($overrides)) {
                return array_merge(self::DEFAULTS, $overrides);
            }
        } catch (\Throwable) {
        }

        return self::DEFAULTS;
    }
}

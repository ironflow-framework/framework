<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Application;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, callable $next): Response
    {
        $app = Application::getInstance();
        $flag = $app->path('storage', 'maintenance.flag');

        if (!is_file($flag)) {
            return $next($request);
        }

        // Allow bypass via a secret cookie
        $secret = trim((string) file_get_contents($flag));
        if ($secret && $request->cookies->get('maintenance_bypass') === $secret) {
            return $next($request);
        }

        throw new HttpException(503, 'Service Unavailable — maintenance mode.');
    }
}

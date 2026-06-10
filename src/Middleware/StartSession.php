<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Http\Request;
use Core\Http\RedirectResponse;
use Core\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

class StartSession
{
    public function __construct(private readonly SessionManager $session) {}

    public function handle(Request $request, callable $next): Response
    {
        $this->session->start($request);

        $response = $next($request);

        // Flash redirect data into session
        if ($response instanceof RedirectResponse) {
            foreach ($response->getFlashData() as $key => $value) {
                $this->session->flash($key, $value);
            }
        }

        $this->session->save($response);

        return $response;
    }
}

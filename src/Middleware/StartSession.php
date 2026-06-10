<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Http\Request;
use Ironflow\Http\RedirectResponse;
use Ironflow\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

class StartSession
{
    public function __construct(private readonly SessionManager $session)
    {
    }

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

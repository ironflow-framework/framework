<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Http\Request;
use Ironflow\Session\SessionManager;
use Ironflow\Template\Engine;
use Symfony\Component\HttpFoundation\Response;

class ShareErrorsFromSession
{
    public function __construct(
        private readonly SessionManager $session,
        private readonly Engine $view
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $errors = $this->session->pull('_errors', []);
        $oldInput = $this->session->pull('_old_input', []);

        $this->view->shareGlobal('_errors', $errors);
        $this->view->shareGlobal('_old_input', $oldInput);

        return $next($request);
    }
}

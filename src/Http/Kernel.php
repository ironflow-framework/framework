<?php

declare(strict_types=1);

namespace Ironflow\Http;

use Ironflow\Container;
use Ironflow\Exceptions\Handler as ExceptionHandler;
use Ironflow\Exceptions\HttpException;
use Ironflow\Middleware\Pipeline;
use Ironflow\Routing\Router;
use Ironflow\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * HTTP Kernel: applies global middlewares, dispatches to the Router,
 * and delegates error handling to the ExceptionHandler.
 */
class Kernel
{
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly array $middlewareConfig,
        private readonly ExceptionHandler $exceptionHandler
    ) {
    }

    public function handle(Request $request): SymfonyResponse
    {
        try {
            $this->bootSession($request);

            $globalMiddlewares = $this->middlewareConfig['global'] ?? [];
            $aliases = $this->middlewareConfig['aliases'] ?? [];

            $this->router->setMiddlewareAliases($aliases);

            $pipeline = new Pipeline($this->container);

            return $pipeline
                ->send($request)
                ->through($globalMiddlewares)
                ->then(fn(Request $req) => $this->dispatchToRouter($req));

        } catch (Throwable $e) {
            return $this->exceptionHandler->render($request, $e);
        }
    }

    private function dispatchToRouter(Request $request): SymfonyResponse
    {
        return $this->router->dispatch($request);
    }

    private function bootSession(Request $request): void
    {
        if ($this->container->has(SessionManager::class)) {
            $session = $this->container->make(SessionManager::class);
            $session->start($request);
        }
    }
}

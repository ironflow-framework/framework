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
 * HTTP Kernel: validates configuration, applies global middlewares,
 * dispatches to the Router, and delegates errors to the ExceptionHandler.
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
            $this->validateAppKey($request);
            $this->bootSession($request);

            $globalMiddlewares = $this->resolveMiddlewareStack(
                $this->middlewareConfig['global'] ?? []
            );

            // Prepend hot-reload middleware in local/dev mode (handles /__ironflow/ping)
            if ($this->isDevMode()) {
                array_unshift($globalMiddlewares, \Ironflow\Http\Middleware\HotReloadMiddleware::class);
            }

            $aliases = $this->middlewareConfig['aliases'] ?? [];
            $this->router->setMiddlewareAliases($aliases);

            if (method_exists($this->router, 'setMiddlewareGroups')) {
                $this->router->setMiddlewareGroups($this->middlewareConfig['groups'] ?? []);
            }

            $pipeline = new Pipeline($this->container);

            return $pipeline
                ->send($request)
                ->through($globalMiddlewares)
                ->then(fn(Request $req) => $this->router->dispatch($req));

        } catch (Throwable $e) {
            return $this->exceptionHandler->render($request, $e);
        }
    }

    /**
     * Expand group names in a middleware stack to their constituent classes.
     * FQCNs and aliases pass through unchanged.
     */
    private function resolveMiddlewareStack(array $middlewares): array
    {
        $groups  = $this->middlewareConfig['groups']  ?? [];
        $aliases = $this->middlewareConfig['aliases'] ?? [];
        $result  = [];

        foreach ($middlewares as $m) {
            if (is_string($m)) {
                if (isset($groups[$m])) {
                    // Expand group recursively
                    foreach ($this->resolveMiddlewareStack($groups[$m]) as $resolved) {
                        $result[] = $resolved;
                    }
                    continue;
                }
                if (isset($aliases[$m])) {
                    $result[] = $aliases[$m];
                    continue;
                }
            }
            $result[] = $m;
        }

        return $result;
    }

    /**
     * Fail fast if APP_KEY is missing — every request needs it for CSRF and sessions.
     * Console commands bypass this check so that `php forge key:generate` still works.
     */
    private function validateAppKey(Request $request): void
    {
        $key = trim((string) ($_ENV['APP_KEY'] ?? ''));
        if ($key !== '') {
            return;
        }

        throw new \RuntimeException(
            'Application key is not set.' . PHP_EOL .
            'Generate one with:  php forge key:generate' . PHP_EOL .
            'Or copy your env:   cp .env.example .env'
        );
    }

    private function isDevMode(): bool
    {
        $env   = strtolower((string) ($_ENV['APP_ENV'] ?? 'production'));
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        return $env === 'local' || $debug;
    }

    private function bootSession(Request $request): void
    {
        if ($this->container->has(SessionManager::class)) {
            $session = $this->container->make(SessionManager::class);
            $session->start($request);
        }
    }
}

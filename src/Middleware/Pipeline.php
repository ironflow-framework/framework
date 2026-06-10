<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Container;
use Core\Http\Request;
use Closure;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pipeline — sends a request through a stack of middlewares,
 * then calls the final destination handler.
 *
 * Supports both onion-style (handle($req, $next)) and hook-style
 * (processRequest / processResponse) middlewares.
 */
class Pipeline
{
    private Request $passable;
    private array   $pipes = [];

    public function __construct(private readonly Container $container) {}

    public function send(Request $request): static
    {
        $this->passable = $request;
        return $this;
    }

    public function through(array $middlewares): static
    {
        $this->pipes = $middlewares;
        return $this;
    }

    public function then(callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    private function carry(): Closure
    {
        return function (Closure $next, $pipe) {
            return function (Request $request) use ($next, $pipe): Response {
                [$class, $params] = $this->parsePipe($pipe);
                $middleware = $this->container->make($class);

                // Hook-style middleware (Django-inspired)
                if (method_exists($middleware, 'processRequest')) {
                    $early = $middleware->processRequest($request);
                    if ($early instanceof Response) {
                        return $early;
                    }
                    $response = $next($request);
                    if (method_exists($middleware, 'processResponse')) {
                        $response = $middleware->processResponse($request, $response);
                    }
                    return $response;
                }

                // Onion-style middleware (handle + next)
                return $middleware->handle($request, $next, ...$params);
            };
        };
    }

    private function prepareDestination(callable $destination): Closure
    {
        return fn(Request $request): Response => $destination($request);
    }

    private function parsePipe(mixed $pipe): array
    {
        if (is_string($pipe) && str_contains($pipe, ':')) {
            [$class, $paramStr] = explode(':', $pipe, 2);
            $params = explode(',', $paramStr);
            return [$class, $params];
        }
        return is_string($pipe) ? [$pipe, []] : [get_class($pipe), []];
    }
}

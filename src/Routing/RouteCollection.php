<?php

declare(strict_types=1);

namespace Ironflow\Routing;

use Ironflow\Exceptions\HttpException;

/**
 * Holds all registered routes and finds the best match for a request.
 */
class RouteCollection
{
    /** @var array<string, Route[]> Method => routes */
    private array $routes = [];

    /** @var array<string, Route> Name => route */
    private array $named = [];

    public function add(Route $route): void
    {
        $method = strtoupper($route->getMethod());
        $this->routes[$method][] = $route;

        if ($name = $route->getName()) {
            $this->named[$name] = $route;
        }
    }

    /** Register a name on the last added route (used by Router::name()). */
    public function setName(string $method, string $name): void
    {
        $method = strtoupper($method);
        $last = end($this->routes[$method] ?? []);
        if ($last instanceof Route) {
            $last->name($name);
            $this->named[$name] = $last;
        }
    }

    /**
     * Match a request. Returns [Route, params] or throws HttpException.
     */
    public function match(string $method, string $uri): array
    {
        $method = strtoupper($method);

        // Try exact method first, then HEAD→GET fallback
        $candidates = $this->routes[$method] ?? [];
        if ($method === 'HEAD') {
            $candidates = array_merge($candidates, $this->routes['GET'] ?? []);
        }

        foreach ($candidates as $route) {
            $params = $route->match($uri);
            if ($params !== null) {
                return [$route, $params];
            }
        }

        // Check if any other method matches (to return 405 vs 404)
        foreach ($this->routes as $m => $routeList) {
            if ($m === $method) {
                continue;
            }
            foreach ($routeList as $route) {
                if ($route->match($uri) !== null) {
                    throw new HttpException(405, "Method {$method} not allowed.");
                }
            }
        }

        throw new HttpException(404, "No route found for [{$method}] {$uri}");
    }

    public function getByName(string $name): ?Route
    {
        return $this->named[$name] ?? null;
    }

    public function all(): array
    {
        return $this->routes;
    }
}

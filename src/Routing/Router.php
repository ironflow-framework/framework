<?php

declare(strict_types=1);

namespace Ironflow\Routing;

use Ironflow\Container;
use Ironflow\Exceptions\HttpException;
use Ironflow\Http\FormRequest;
use Ironflow\Http\Request;
use Ironflow\Middleware\Pipeline;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fluent HTTP router with groups, resource routes, named routes,
 * middleware aliases, and controller method DI injection.
 */
class Router
{
    private RouteCollection $routes;

    /** Group attribute stack (prefix, middleware, namespace) */
    private array $groupStack = [];

    /** @var array<string, string> Middleware alias → class name */
    private array $middlewareAliases = [];

    public function __construct(private readonly Container $container)
    {
        $this->routes = new RouteCollection();
    }

    // ─────────────────────── HTTP verb helpers ───────────────────────

    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute('ANY', $uri, $action);
    }

    public function match(array $methods, string $uri, mixed $action): void
    {
        foreach ($methods as $method) {
            $this->addRoute($method, $uri, $action);
        }
    }

    // ─────────────────────── Groups ──────────────────────────────────

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    // ─────────────────────── Resource routes ─────────────────────────

    public function resource(string $name, string $controller): void
    {
        $singular = rtrim($name, 's');
        $prefix = '/' . ltrim($name, '/');

        $this->get("{$prefix}", [$controller, 'index'])->name("{$name}.index");
        $this->get("{$prefix}/create", [$controller, 'create'])->name("{$name}.create");
        $this->post("{$prefix}", [$controller, 'store'])->name("{$name}.store");
        $this->get("{$prefix}/{{$singular}}", [$controller, 'show'])->name("{$name}.show");
        $this->get("{$prefix}/{{$singular}}/edit", [$controller, 'edit'])->name("{$name}.edit");
        $this->put("{$prefix}/{{$singular}}", [$controller, 'update'])->name("{$name}.update");
        $this->patch("{$prefix}/{{$singular}}", [$controller, 'update']);
        $this->delete("{$prefix}/{{$singular}}", [$controller, 'destroy'])->name("{$name}.destroy");
    }

    // ─────────────────────── URL generation ──────────────────────────

    public function route(string $name, array $params = []): string
    {
        $route = $this->routes->getByName($name);

        if ($route === null) {
            throw new \RuntimeException("No route named [{$name}] found.");
        }

        return $route->generateUrl($params);
    }

    // ─────────────────────── Dispatching ────────────────────────────

    public function dispatch(Request $request): Response
    {
        [$route, $params] = $this->routes->match($request->getMethod(), $request->getPathInfo());
        $request->setRouteParams($params);

        $middlewares = $this->resolveMiddlewares($route->getMiddlewares());

        $pipeline = new Pipeline($this->container);

        return $pipeline
            ->send($request)
            ->through($middlewares)
            ->then(fn(Request $req) => $this->callAction($route->getAction(), $req, $params));
    }

    // ─────────────────────── Utilities ──────────────────────────────

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function setMiddlewareAliases(array $aliases): void
    {
        $this->middlewareAliases = $aliases;
    }

    public function loadRoutesFrom(string $file): void
    {
        $router = $this;
        require $file;
    }

    // ─────────────────────── Private ────────────────────────────────

    private function addRoute(string $method, string $uri, mixed $action): Route
    {
        $uri = $this->applyGroupPrefix($uri);
        $action = $this->applyGroupNamespace($action);

        $route = new Route($method, $uri, $action);

        // Apply group middlewares
        foreach ($this->groupStack as $group) {
            if (!empty($group['middleware'])) {
                $route->middleware($group['middleware']);
            }
        }

        $this->routes->add($route);
        return $route;
    }

    private function applyGroupPrefix(string $uri): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . ltrim($group['prefix'], '/');
            }
        }
        return $prefix . '/' . ltrim($uri, '/');
    }

    private function applyGroupNamespace(mixed $action): mixed
    {
        if (!is_array($action) || !isset($action[0])) {
            return $action;
        }
        foreach (array_reverse($this->groupStack) as $group) {
            if (!empty($group['namespace'])) {
                $action[0] = rtrim($group['namespace'], '\\') . '\\' . ltrim($action[0], '\\');
                break;
            }
        }
        return $action;
    }

    private function resolveMiddlewares(array $middlewares): array
    {
        $resolved = [];
        foreach ($middlewares as $middleware) {
            // Handle "alias:param1,param2" syntax
            [$alias, $params] = array_pad(explode(':', $middleware, 2), 2, null);

            $class = $this->middlewareAliases[$alias] ?? $alias;
            $resolved[] = $params !== null ? "{$class}:{$params}" : $class;
        }
        return $resolved;
    }

    private function callAction(mixed $action, Request $request, array $params): Response
    {
        if (is_callable($action)) {
            $result = $action($request, ...$params);
            return $this->toResponse($result);
        }

        if (is_array($action)) {
            [$controllerClass, $method] = $action;
            $controller = $this->container->make($controllerClass);
            $args = $this->resolveMethodParams(
                new ReflectionMethod($controller, $method),
                $request,
                $params
            );
            $result = $controller->$method(...$args);
            return $this->toResponse($result);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$controllerClass, $method] = explode('@', $action, 2);
            return $this->callAction([$controllerClass, $method], $request, $params);
        }

        throw new \RuntimeException('Invalid route action.');
    }

    private function resolveMethodParams(ReflectionMethod $method, Request $request, array $routeParams): array
    {
        $args = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if (is_a($typeName, FormRequest::class, true)) {
                    // Auto-validate FormRequest subclass before injecting
                    $formRequest = $typeName::createFrom($request);
                    $formRequest->validateResolved();
                    $args[] = $formRequest;
                } elseif ($typeName === Request::class || is_subclass_of($typeName, Request::class)) {
                    $args[] = $request;
                } else {
                    $args[] = $this->container->make($typeName);
                }
            } elseif (\array_key_exists($param->getName(), $routeParams)) {
                $args[] = $routeParams[$param->getName()];
            } elseif ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $args;
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return new \Ironflow\Http\JsonResponse($result);
        }
        return new \Ironflow\Http\Response((string) $result, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

<?php

declare(strict_types=1);

namespace Ironflow\Routing;

/**
 * Represents a single route with its URI pattern, HTTP method,
 * action, name, middlewares, and constraints.
 */
class Route
{
    private ?string $name = null;
    private array $middlewares = [];
    private array $wheres = [];
    private string $compiledPattern = '';

    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly mixed $action
    ) {
        $this->compiledPattern = $this->compile($uri);
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    public function where(string $param, string $regex): static
    {
        $this->wheres[$param] = $regex;
        $this->compiledPattern = $this->compile($this->uri);
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
    public function getUri(): string
    {
        return $this->uri;
    }
    public function getAction(): mixed
    {
        return $this->action;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /** Try to match the given URI. Returns extracted params or null. */
    public function match(string $uri): ?array
    {
        if (!preg_match($this->compiledPattern, $uri, $matches)) {
            return null;
        }

        // Filter out numeric keys from preg_match
        return array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
    }

    /** Generate a URL for this route given the parameter values. */
    public function generateUrl(array $params = []): string
    {
        $uri = $this->uri;

        // Replace {param} and {param?} segments
        $uri = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use (&$params) {
            $key = $m[1];
            if (isset($params[$key])) {
                $val = $params[$key];
                unset($params[$key]);
                return (string) $val;
            }
            return '';
        }, $uri);

        // Remaining params as query string
        if (!empty($params)) {
            $uri .= '?' . http_build_query($params);
        }

        return rtrim((string) $uri, '/') ?: '/';
    }

    private function compile(string $uri): string
    {
        // Escape forward slashes for the regex delimiter
        $pattern = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) {
            $name = $m[1];
            $optional = isset($m[2]) && $m[2] === '?';
            $regex = $this->wheres[$name] ?? '[^/]+';
            $segment = "(?P<{$name}>{$regex})";
            return $optional ? "(?:/{$segment})?" : $segment;
        }, $uri);

        $pattern = str_replace('/', '\/', (string) $pattern);
        return '/^' . $pattern . '\/?$/';
    }
}

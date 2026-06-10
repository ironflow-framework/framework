<?php

declare(strict_types=1);

namespace Ironflow\Http;

use Ironflow\Validation\ValidatorFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Extends Symfony Request with framework helpers: wantsJson, bearerToken,
 * route parameters, validated input, and validate() shortcut.
 */
class Request extends SymfonyRequest
{
    private array $routeParams = [];
    private ?array $validatedData = null;

    public static function createFromGlobals(): static
    {
        $request = parent::createFromGlobals();
        // @phpstan-ignore-next-line
        return $request;
    }

    /** Route parameters set by the Router after matching. */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /** Get a route parameter (e.g. {id} in the URI). */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function allRouteParams(): array
    {
        return $this->routeParams;
    }

    /** True if the client expects a JSON response. */
    public function wantsJson(): bool
    {
        $accept = $this->headers->get('Accept', '');
        return str_contains((string) $accept, 'application/json')
            || str_contains((string) $accept, 'application/javascript')
            || $this->isXmlHttpRequest();
    }

    /** Extract the JWT from the Authorization header. */
    public function bearerToken(): ?string
    {
        $auth = $this->headers->get('Authorization', '');
        if (str_starts_with((string) $auth, 'Bearer ')) {
            return substr((string) $auth, 7);
        }
        return null;
    }

    /** Input from request body or query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->request->has($key)) {
            return $this->request->get($key);
        }
        if ($this->query->has($key)) {
            return $this->query->get($key);
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->query->all(), $this->request->all());
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return $this->request->has($key) || $this->query->has($key);
    }

    public function isJson(): bool
    {
        return str_contains($this->headers->get('Content-Type', ''), 'application/json');
    }

    /** Decode JSON body if the Content-Type is application/json. */
    public function json(string $key = null, mixed $default = null): mixed
    {
        $data = json_decode((string) $this->getContent(), true) ?? [];
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    /**
     * Validate request data. On failure, throws ValidationException.
     * Internally used by controllers; the HTTP Kernel catches and redirects/422s.
     */
    public function validate(array $rules, array $messages = []): array
    {
        $factory = new ValidatorFactory();
        $validator = $factory->make($this->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new \Ironflow\Validation\ValidationException($validator);
        }

        $this->validatedData = $validator->validated();
        return $this->validatedData;
    }

    public function validated(): mixed
    {
        return $this->validatedData ?? [];
    }

    public function getMethod(): string
    {
        // Support form method spoofing via _method hidden input
        if (parent::getMethod() === 'POST') {
            $spoofed = strtoupper((string) ($this->request->get('_method', '')));
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }
        return parent::getMethod();
    }
}

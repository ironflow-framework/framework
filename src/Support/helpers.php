<?php

declare(strict_types=1);

use Core\Application;
use Core\Config\Repository as ConfigRepository;
use Core\Exceptions\HttpException;

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $instance = Application::getInstance();
        if ($abstract === null) {
            return $instance;
        }
        return $instance->getContainer()->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app(ConfigRepository::class)->get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        return app(\Core\Routing\Router::class)->route($name, $params);
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        throw new HttpException($code, $message);
    }
}

if (!function_exists('abort_if')) {
    function abort_if(bool $condition, int $code, string $message = ''): void
    {
        if ($condition) {
            abort($code, $message);
        }
    }
}

if (!function_exists('abort_unless')) {
    function abort_unless(bool $condition, int $code, string $message = ''): void
    {
        if (!$condition) {
            abort($code, $message);
        }
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \Core\Http\RedirectResponse
    {
        return new \Core\Http\RedirectResponse($url, $status);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return app(\Core\Template\Engine::class)->render($template, $data);
    }
}

if (!function_exists('now')) {
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return Application::getInstance()->getBasePath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return Application::getInstance()->path('storage', $path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return Application::getInstance()->path('public', $path);
    }
}

if (!function_exists('bcrypt')) {
    function bcrypt(string $password): string
    {
        return \Core\Auth\Hash::make($password);
    }
}

if (!function_exists('class_basename')) {
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        $parts = explode('\\', $class);
        return end($parts);
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $text, string $separator = '-'): string
    {
        $text = preg_replace('/[^\pL\d]+/u', $separator, $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $text);
        $text = preg_replace('/[^a-z0-9' . preg_quote($separator, '/') . ']+/i', '', (string) $text);
        return strtolower(trim((string) $text, $separator));
    }
}

if (!function_exists('collect')) {
    function collect(array $items = []): \Core\Support\Collection
    {
        return new \Core\Support\Collection($items);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app(\Core\Session\SessionManager::class)->csrfToken();
    }
}

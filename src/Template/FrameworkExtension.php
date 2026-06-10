<?php

declare(strict_types=1);

namespace Ironflow\Template;

use Ironflow\Application;
use Ironflow\Container;
use Ironflow\Template\ComponentRegistry;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Twig extension exposing all IronFlow helpers:
 * functions (route, asset, csrf_token, auth_user, config, old, errors, ...),
 * filters (truncate, slug, markdown, time_ago, money),
 * tests (admin),
 * globals (app, current_route).
 */
class FrameworkExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    // ──────────────────────── Functions ─────────────────────────────

    public function getFunctions(): array
    {
        $safe = ['is_safe' => ['html']];

        return [
            new TwigFunction('route', [$this, 'funcRoute']),
            new TwigFunction('asset', [$this, 'funcAsset']),
            new TwigFunction('vite_asset', [$this, 'funcViteAsset']),
            new TwigFunction('csrf_token', [$this, 'funcCsrfToken']),
            new TwigFunction('csrf_field', [$this, 'funcCsrfField'], $safe),
            new TwigFunction('method_field', [$this, 'funcMethodField'], $safe),
            new TwigFunction('auth_user', [$this, 'funcAuthUser']),
            new TwigFunction('auth_check', [$this, 'funcAuthCheck']),
            new TwigFunction('config', [$this, 'funcConfig']),
            new TwigFunction('old', [$this, 'funcOld']),
            new TwigFunction('errors', [$this, 'funcErrors']),
            new TwigFunction('has_error', [$this, 'funcHasError']),
            new TwigFunction('component', [$this, 'funcComponent'], $safe),
            new TwigFunction('dump', [$this, 'funcDump'], ['is_safe' => ['html'], 'needs_context' => true]),
        ];
    }

    public function funcRoute(string $name, array $params = []): string
    {
        return $this->container->make(\Ironflow\Routing\Router::class)->route($name, $params);
    }

    public function funcAsset(string $path): string
    {
        $publicPath = Application::getInstance()->path('public', $path);
        $mtime = is_file($publicPath) ? filemtime($publicPath) : 0;
        $base = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        return $base . '/' . ltrim($path, '/') . ($mtime ? "?v={$mtime}" : '');
    }

    /**
     * Return the hashed URL for a Vite-compiled asset.
     * In dev (HMR) mode points to the Vite dev server; in prod reads the manifest.
     */
    public function funcViteAsset(string $entry): string
    {
        static $manifest = null;

        $app = Application::getInstance();
        $hotFilePath = $app->path('public', 'build/hot');

        // Vite dev-server HMR mode
        if (is_file($hotFilePath)) {
            $base = rtrim((string) file_get_contents($hotFilePath), "/\n");
            return $base . '/' . ltrim($entry, '/');
        }

        // Production: read Vite 5+ manifest (.vite/manifest.json)
        if ($manifest === null) {
            $manifestPath = $app->path('public', 'build/.vite/manifest.json');
            $manifest = is_file($manifestPath)
                ? (json_decode((string) file_get_contents($manifestPath), true) ?? [])
                : [];
        }

        if (isset($manifest[$entry]['file'])) {
            return '/build/' . $manifest[$entry]['file'];
        }

        // Fallback: serve directly (dev without HMR or missing manifest)
        return '/build/' . $entry;
    }

    /**
     * Render a view component by name.
     * {{ component('alert', {type: 'success', message: 'Saved!'}) }}
     */
    public function funcComponent(string $name, array $props = []): string
    {
        try {
            $registry = $this->container->make(ComponentRegistry::class);
            $class = $registry->resolve($name);

            if ($class === null) {
                throw new \RuntimeException("Component [{$name}] is not registered.");
            }

            $component = new $class($props);
            $engine = $this->container->make(Engine::class);

            return $engine->render($component->render(), array_merge($props, $component->data()));
        } catch (\Throwable $e) {
            if ((bool) ($_ENV['APP_DEBUG'] ?? false)) {
                return '<div style="color:red;border:1px solid red;padding:4px;font-family:monospace">'
                    . 'Component [' . htmlspecialchars($name) . '] error: '
                    . htmlspecialchars($e->getMessage())
                    . '</div>';
            }
            return '';
        }
    }

    public function funcCsrfToken(): string
    {
        return $this->container->make(\Ironflow\Session\SessionManager::class)->csrfToken();
    }

    public function funcCsrfField(): string
    {
        $token = $this->funcCsrfToken();
        return "<input type=\"hidden\" name=\"_token\" value=\"{$token}\">";
    }

    public function funcMethodField(string $method): string
    {
        return "<input type=\"hidden\" name=\"_method\" value=\"{$method}\">";
    }

    public function funcAuthUser(): mixed
    {
        try {
            return $this->container->make(\Ironflow\Auth\AuthManager::class)->user();
        } catch (\Throwable) {
            return null;
        }
    }

    public function funcAuthCheck(): bool
    {
        try {
            return $this->container->make(\Ironflow\Auth\AuthManager::class)->check();
        } catch (\Throwable) {
            return false;
        }
    }

    public function funcConfig(string $key, mixed $default = null): mixed
    {
        return $this->container->make(\Ironflow\Config\Repository::class)->get($key, $default);
    }

    public function funcOld(string $key, mixed $default = ''): mixed
    {
        try {
            $session = $this->container->make(\Ironflow\Session\SessionManager::class);
            return $session->get('_old_input.' . $key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function funcErrors(?string $key = null): mixed
    {
        try {
            $session = $this->container->make(\Ironflow\Session\SessionManager::class);
            $errors = $session->get('_errors', []);
            if ($key === null) {
                return $errors;
            }
            return $errors[$key] ?? [];
        } catch (\Throwable) {
            return $key ? [] : [];
        }
    }

    public function funcHasError(string $key): bool
    {
        return !empty($this->funcErrors($key));
    }

    public function funcDump(array $context, mixed ...$vars): string
    {
        if (empty($vars)) {
            $vars = array_values($context);
        }
        ob_start();
        foreach ($vars as $v) {
            \Symfony\Component\VarDumper\VarDumper::dump($v);
        }
        return (string) ob_get_clean();
    }

    // ──────────────────────── Filters ────────────────────────────────

    public function getFilters(): array
    {
        return [
            new TwigFilter('truncate', [$this, 'filterTruncate']),
            new TwigFilter('slug', [$this, 'filterSlug']),
            new TwigFilter('markdown', [$this, 'filterMarkdown'], ['is_safe' => ['html']]),
            new TwigFilter('time_ago', [$this, 'filterTimeAgo']),
            new TwigFilter('money', [$this, 'filterMoney']),
        ];
    }

    public function filterTruncate(string $text, int $length = 120, string $suffix = '…'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $truncated = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        return $truncated . $suffix;
    }

    public function filterSlug(string $text): string
    {
        return str_slug($text);
    }

    public function filterMarkdown(string $text): string
    {
        // Minimal inline Markdown renderer
        $text = htmlspecialchars($text, ENT_QUOTES);

        // Headings
        $text = preg_replace('/^#{3}\s+(.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^#{2}\s+(.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^#{1}\s+(.+)$/m', '<h1>$1</h1>', $text);

        // Bold, italic
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);

        // Inline code
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);

        // Links
        $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);

        // Unordered list items
        $text = preg_replace('/^[-*]\s+(.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);

        // Paragraphs
        $text = preg_replace('/\n\n+/', '</p><p>', $text);

        return '<p>' . $text . '</p>';
    }

    public function filterTimeAgo(\DateTimeInterface|string $date): string
    {
        if (is_string($date)) {
            $date = new \DateTimeImmutable($date);
        }
        $diff = (new \DateTimeImmutable())->getTimestamp() - $date->getTimestamp();

        return match (true) {
            $diff < 60 => 'à l\'instant',
            $diff < 3600 => 'il y a ' . (int) ($diff / 60) . ' minute' . ((int) ($diff / 60) > 1 ? 's' : ''),
            $diff < 86400 => 'il y a ' . (int) ($diff / 3600) . ' heure' . ((int) ($diff / 3600) > 1 ? 's' : ''),
            $diff < 2592000 => 'il y a ' . (int) ($diff / 86400) . ' jour' . ((int) ($diff / 86400) > 1 ? 's' : ''),
            $diff < 31536000 => 'il y a ' . (int) ($diff / 2592000) . ' mois',
            default => 'il y a ' . (int) ($diff / 31536000) . ' an' . ((int) ($diff / 31536000) > 1 ? 's' : ''),
        };
    }

    public function filterMoney(float $amount, string $dec = ',', string $thou = ' ', string $symbol = '€'): string
    {
        return number_format($amount, 2, $dec, $thou) . ' ' . $symbol;
    }

    // ──────────────────────── Tests ──────────────────────────────────

    public function getTests(): array
    {
        return [
            new TwigTest('admin', [$this, 'testIsAdmin']),
        ];
    }

    public function testIsAdmin(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }
        return (bool) (is_array($user) ? ($user['is_admin'] ?? false) : ($user->is_admin ?? false));
    }

    // ──────────────────────── Globals ────────────────────────────────

    public function getGlobals(): array
    {
        $app = Application::getInstance();

        $currentRoute = null;

        return [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'IronFlow',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
                'version' => $_ENV['APP_VERSION'] ?? '0.1.0',
            ],
            'current_route' => $currentRoute,
        ];
    }
}

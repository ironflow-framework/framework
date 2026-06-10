<?php

declare(strict_types=1);

namespace Core;

use Ironflow\Auth\AuthManager;
use Ironflow\Cache\CacheManager;
use Ironflow\Config\Repository as ConfigRepository;
use Ironflow\Console\Kernel as ConsoleKernel;
use Ironflow\Container;
use Ironflow\Events\Dispatcher;
use Ironflow\Exceptions\Handler as ExceptionHandler;
use Ironflow\Facades\Facade;
use Ironflow\Http\Kernel as HttpKernel;
use Ironflow\Http\Request;
use Ironflow\Logging\Logger;
use Ironflow\Module\ModuleManager;
use Ironflow\Routing\Router;
use Ironflow\Session\SessionManager;
use Ironflow\Template\ComponentRegistry;
use Ironflow\Template\Engine as TemplateEngine;
use Ironflow\Database\Connection;
use Ironflow\Validation\ValidatorFactory;
use Dotenv\Dotenv;
use Throwable;

/**
 * The Application is the central IoC kernel. It holds the base path,
 * wires up all core service bindings and boots the module system.
 */
class Application
{
    private static self $instance;
    private Container $container;
    private ConfigRepository $config;
    private bool $booted = false;

    /** Conventioned sub-paths, all relative to basePath and overridable. */
    private array $paths = [];

    public function __construct(private readonly string $basePath)
    {
        self::$instance = $this;
        $this->paths = [
            'config' => $basePath . '/config',
            'modules' => $basePath . '/modules',
            'storage' => $basePath . '/storage',
            'cache' => $basePath . '/storage/cache',
            'logs' => $basePath . '/storage/logs',
            'views' => $basePath . '/resources/views',
            'public' => $basePath . '/public',
        ];

        $this->container = new Container();
        $this->container->instance(Application::class, $this);
        $this->container->instance(Container::class, $this->container);

        $this->loadEnvironment();
        $this->bindCoreServices();
        Facade::setContainer($this->container);
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getBasePath(string $path = ''): string
    {
        return $path ? $this->basePath . '/' . ltrim($path, '/') : $this->basePath;
    }

    public function path(string $key, string $append = ''): string
    {
        $base = $this->paths[$key] ?? $this->basePath . '/' . $key;
        return $append ? $base . '/' . ltrim($append, '/') : $base;
    }

    public function setPath(string $key, string $path): void
    {
        $this->paths[$key] = $path;
    }

    public function configure(string $name): void
    {
        $file = $this->path('config') . '/' . $name . '.php';
        if (is_file($file)) {
            $values = require $file;
            $this->config->set($name, $values);
        }
    }

    public function handleRequest(): void
    {
        $this->boot();

        /** @var HttpKernel $kernel */
        $kernel = $this->container->make(HttpKernel::class);
        $request = Request::createFromGlobals();
        $response = $kernel->handle($request);
        $response->send();
    }

    public function runConsole(): never
    {
        $this->boot();

        /** @var ConsoleKernel $kernel */
        $kernel = $this->container->make(ConsoleKernel::class);
        $status = $kernel->handle();
        exit($status);
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        /** @var ModuleManager $manager */
        $manager = $this->container->make(ModuleManager::class);

        $moduleClasses = $this->config->get('modules.enabled', []);
        foreach ($moduleClasses as $moduleClass) {
            $manager->register($moduleClass);
        }

        $manager->boot();
    }

    private function loadEnvironment(): void
    {
        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->safeLoad();
    }

    private function bindCoreServices(): void
    {
        // Config
        $this->config = new ConfigRepository();
        $this->container->instance(ConfigRepository::class, $this->config);

        // Logger (Monolog-backed)
        $this->container->singleton(Logger::class, function () {
            return new Logger(
                $this->config->get('app.name', 'IronFlow'),
                $this->path('logs'),
                $this->config->get('logging.level', 'debug'),
                (bool) $this->config->get('app.debug', false)
            );
        });

        // Event Dispatcher
        $this->container->singleton(Dispatcher::class, fn() => new Dispatcher($this->container));

        // Router
        $this->container->singleton(Router::class, fn() => new Router($this->container));

        // Template Engine
        $this->container->singleton(TemplateEngine::class, function () {
            return new TemplateEngine(
                $this->container,
                $this->path('views'),
                $this->path('cache', 'twig'),
                (bool) $this->config->get('app.debug', false)
            );
        });

        // Database Connection (lazy)
        $this->container->singleton(Connection::class, function () {
            return new Connection($this->config->get('database', []));
        });

        // Module Manager
        $this->container->singleton(ModuleManager::class, function () {
            return new ModuleManager($this->container, $this->path('modules'));
        });

        // HTTP Kernel
        $this->container->singleton(HttpKernel::class, function () {
            return new HttpKernel(
                $this->container,
                $this->container->make(Router::class),
                $this->config->get('middleware', []),
                $this->container->make(ExceptionHandler::class)
            );
        });

        // Exception Handler
        $this->container->singleton(ExceptionHandler::class, function () {
            return new ExceptionHandler(
                $this->container->make(Logger::class),
                $this->container->make(TemplateEngine::class),
                (bool) $this->config->get('app.debug', false),
                $this->path('views', 'errors')
            );
        });

        // Console Kernel
        $this->container->singleton(ConsoleKernel::class, function () {
            return new ConsoleKernel(
                $this->container,
                $this->config->get('app.name', 'IronFlow'),
                $this->config->get('app.version', '0.1.0')
            );
        });

        // Session Manager
        $this->container->singleton(SessionManager::class, fn() => new SessionManager());

        // Cache Manager
        $this->container->singleton(CacheManager::class, function () {
            return new CacheManager($this->path('cache', 'app'));
        });

        // Auth Manager
        $this->container->singleton(AuthManager::class, fn() => new AuthManager(
            $this->container->make(Connection::class),
            $this->container->make(SessionManager::class),
            $this->config->get('auth', [])
        ));

        // Validator Factory (no-arg; resolves DB lazily via Application::getInstance())
        $this->container->singleton(ValidatorFactory::class, fn() => new ValidatorFactory());

        // View Component Registry
        $this->container->singleton(ComponentRegistry::class, fn() => new ComponentRegistry());
    }

    public function version(): string
    {
        return $this->config->get('app.version', '0.1.0');
    }

    public function environment(): string
    {
        return $this->config->get('app.env', 'production');
    }

    public function isDebug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }
}

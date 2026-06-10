<?php

declare(strict_types=1);

namespace Core\Module;

use Core\Container;
use Core\Events\Dispatcher;
use Core\Routing\Router;
use Core\Template\Engine;

/**
 * Base class for all application modules.
 * register() is called first for every module, boot() second — same two-phase
 * lifecycle as AdonisJS (register for bindings, boot for everything that needs
 * other services to be ready: routes, listeners, view namespaces).
 */
abstract class BaseModule
{
    protected Container $container;
    protected Router    $router;
    protected Dispatcher $events;
    protected Engine    $view;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
        $this->router    = $container->make(Router::class);
        $this->events    = $container->make(Dispatcher::class);
        $this->view      = $container->make(Engine::class);
    }

    /** Phase 1: register service bindings into the container. */
    public function register(): void {}

    /** Phase 2: load routes, view namespaces, listeners, etc. */
    public function boot(): void {}

    /** Called by ModuleManager to load routes from the module's routes.php. */
    public function loadRoutes(string $routesFile): void
    {
        if (is_file($routesFile)) {
            $router = $this->router;
            require $routesFile;
        }
    }

    /** Register a Twig namespace for this module's Views directory. */
    public function registerViewNamespace(string $namespace, string $path): void
    {
        if (is_dir($path)) {
            $this->view->addNamespace($namespace, $path);
        }
    }
}

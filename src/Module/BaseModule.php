<?php

declare(strict_types=1);

namespace Ironflow\Module;

use Ironflow\Container;
use Ironflow\Events\Dispatcher;
use Ironflow\Routing\Router;
use Ironflow\Template\Engine;

/**
 * Base class for all application modules.
 * register() is called first for every module, boot() second — same two-phase
 * lifecycle as AdonisJS (register for bindings, boot for everything that needs
 * other services to be ready: routes, listeners, view namespaces).
 */
abstract class BaseModule
{
    protected Container $container;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /** @return Router */
    protected function getRouter(): Router
    {
        return $this->container->make(Router::class);
    }

    /** @return Dispatcher */
    protected function getEvents(): Dispatcher
    {
        return $this->container->make(Dispatcher::class);
    }

    /** @return Engine */
    protected function getView(): Engine
    {
        return $this->container->make(Engine::class);
    }

    /** Phase 1: register service bindings into the container. */
    public function register(): void
    {
    }

    /** Phase 2: load routes, view namespaces, listeners, etc. */
    public function boot(): void
    {
    }

    /** Called by ModuleManager to load routes from the module's routes.php. */
    public function loadRoutes(string $routesFile): void
    {
        if (is_file($routesFile)) {
            $router = $this->getRouter();
            require $routesFile;
        }
    }

    /** Register a Twig namespace for this module's Views directory. */
    public function registerViewNamespace(string $namespace, string $path): void
    {
        if (is_dir($path) && $this->container->has(Engine::class)) {
            $this->getView()->addNamespace($namespace, $path);
        }
    }
}

<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;
use Ironflow\Routing\Router;

class RouteListCommand extends Command
{
    protected string $signature = 'route:list';
    protected string $description = 'List all registered routes';

    public function __construct(private readonly Router $router)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $rows = [];

        foreach ($this->router->getRoutes()->all() as $method => $routes) {
            foreach ($routes as $route) {
                $action = $route->getAction();
                $action = is_array($action)
                    ? implode('@', [class_basename($action[0]), $action[1]])
                    : (is_callable($action) ? 'Closure' : (string) $action);

                $rows[] = [
                    $method,
                    $route->getUri(),
                    $route->getName() ?? '',
                    $action,
                    implode(', ', $route->getMiddlewares()),
                ];
            }
        }

        $this->table(['Method', 'URI', 'Name', 'Action', 'Middlewares'], $rows);
        return self::SUCCESS;
    }
}

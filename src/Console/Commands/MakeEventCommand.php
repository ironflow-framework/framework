<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeEventCommand extends Command
{
    protected string $signature   = 'make:event {name} {--module=}';
    protected string $description = 'Create a new event class';

    protected function handle(): int
    {
        $name   = (string) $this->argument('name');
        $module = $this->option('module');

        $path = $module
            ? base_path("modules/{$module}/Events/{$name}.php")
            : base_path("app/Events/{$name}.php");
        $ns = $module ? "Modules\\{$module}\\Events" : "App\\Events";

        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

final readonly class {$name}
{
    public function __construct(
        // public readonly mixed \$payload
    ) {}
}
PHP);
        $this->success("Event [{$name}] created.");
        return self::SUCCESS;
    }
}

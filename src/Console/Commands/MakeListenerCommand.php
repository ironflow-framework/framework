<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeListenerCommand extends Command
{
    protected string $signature   = 'make:listener {name} {--event=} {--module=}';
    protected string $description = 'Create a new event listener class';

    protected function handle(): int
    {
        $name   = (string) $this->argument('name');
        $event  = $this->option('event') ?? 'SomeEvent';
        $module = $this->option('module');

        $path = $module
            ? base_path("modules/{$module}/Listeners/{$name}.php")
            : base_path("app/Listeners/{$name}.php");
        $ns = $module ? "Modules\\{$module}\\Listeners" : "App\\Listeners";

        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

class {$name}
{
    public function handle({$event} \$event): void
    {
        //
    }
}
PHP);
        $this->success("Listener [{$name}] created.");
        return self::SUCCESS;
    }
}

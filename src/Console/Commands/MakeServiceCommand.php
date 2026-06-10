<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class MakeServiceCommand extends Command
{
    protected string $signature = 'make:service {name} {--module=}';
    protected string $description = 'Create a new service class';

    protected function handle(): int
    {
        $name = (string) $this->argument('name');
        $module = $this->option('module');

        if ($module) {
            $path = base_path("modules/{$module}/Services/{$name}.php");
            $ns = "Modules\\{$module}\\Services";
        } else {
            $path = base_path("app/Services/{$name}.php");
            $ns = "App\\Services";
            @mkdir(dirname($path), 0755, true);
        }

        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Ironflow\\Attributes\\Injectable;

#[Injectable]
class {$name}
{
    //
}
PHP);
        $this->success("Service [{$name}] created.");
        return self::SUCCESS;
    }
}

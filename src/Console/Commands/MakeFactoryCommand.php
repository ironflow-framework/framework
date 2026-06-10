<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeFactoryCommand extends Command
{
    protected string $signature   = 'make:factory {name} {--module=}';
    protected string $description = 'Create a new model factory class';

    protected function handle(): int
    {
        $name   = (string) $this->argument('name');
        $module = $this->option('module');

        $path = $module
            ? base_path("modules/{$module}/Database/Factories/{$name}.php")
            : base_path("database/factories/{$name}.php");
        $ns = $module ? "Modules\\{$module}\\Database\\Factories" : "Database\\Factories";

        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Core\\Database\\Factory;

class {$name} extends Factory
{
    public function definition(): array
    {
        return [
            // 'title' => \$this->fake()->sentence(),
        ];
    }
}
PHP);
        $this->success("Factory [{$name}] created.");
        return self::SUCCESS;
    }
}

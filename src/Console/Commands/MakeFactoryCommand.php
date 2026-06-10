<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class MakeFactoryCommand extends Command
{
    protected string $signature = 'make:factory {name?} {--module=}';
    protected string $description = 'Create a new model factory class';

    protected function handle(): int
    {
        $name = $this->argumentOrAsk('name', 'Factory name (e.g. PostFactory):');
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

use Ironflow\\Database\\Factory;

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

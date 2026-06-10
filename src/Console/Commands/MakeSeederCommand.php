<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class MakeSeederCommand extends Command
{
    protected string $signature = 'make:seeder {name} {--module=}';
    protected string $description = 'Create a new database seeder class';

    protected function handle(): int
    {
        $name = (string) $this->argument('name');
        $module = $this->option('module');

        $path = $module
            ? base_path("modules/{$module}/Database/Seeders/{$name}.php")
            : base_path("database/seeders/{$name}.php");
        $ns = $module ? "Modules\\{$module}\\Database\\Seeders" : "Database\\Seeders";

        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Ironflow\\Database\\Seeder;

class {$name} extends Seeder
{
    public function run(): void
    {
        //
    }
}
PHP);
        $this->success("Seeder [{$name}] created.");
        return self::SUCCESS;
    }
}

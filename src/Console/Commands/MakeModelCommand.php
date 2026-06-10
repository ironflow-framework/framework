<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeModelCommand extends Command
{
    protected string $signature   = 'make:model {name} {--module=} {--migration} {--factory}';
    protected string $description = 'Create a new model class';

    protected function handle(): int
    {
        $name    = (string) $this->argument('name');
        $module  = $this->option('module');

        if ($module) {
            $path = base_path("modules/{$module}/Models/{$name}.php");
            $ns   = "Modules\\{$module}\\Models";
        } else {
            $path = base_path("app/Models/{$name}.php");
            $ns   = "App\\Models";
            @mkdir(base_path('app/Models'), 0755, true);
        }

        if (is_file($path)) {
            $this->error("Model [{$name}] already exists.");
            return self::FAILURE;
        }

        file_put_contents($path, $this->stub($ns, $name));
        $this->success("Model [{$name}] created.");

        if ($this->option('migration')) {
            $table = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name)) . 's';
            $stamp = date('Y_m_d_His');
            $migName = "{$stamp}_create_{$table}_table";
            $migPath = $module
                ? base_path("modules/{$module}/Database/Migrations/{$migName}.php")
                : base_path("database/migrations/{$migName}.php");

            @mkdir(dirname($migPath), 0755, true);
            file_put_contents($migPath, $this->migrationStub($table));
            $this->success("Migration [{$migName}] created.");
        }

        return self::SUCCESS;
    }

    private function stub(string $ns, string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Core\\Database\\Model;

class {$name} extends Model
{
    protected array \$fillable = [];
    protected array \$casts    = [];
}
PHP;
    }

    private function migrationStub(string $table): string
    {
        $class = 'Create' . str_replace('_', '', ucwords($table, '_')) . 'Table';
        return <<<PHP
<?php

declare(strict_types=1);

use Core\\Database\\Migrations\\Migration;
use Core\\Database\\Schema\\Schema;
use Core\\Database\\Schema\\Blueprint;

class {$class} extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$t) {
            \$t->id();
            \$t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('{$table}');
    }
}
PHP;
    }
}

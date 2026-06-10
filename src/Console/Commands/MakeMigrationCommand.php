<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $signature   = 'make:migration {name} {--module=}';
    protected string $description = 'Create a new migration file';

    protected function handle(): int
    {
        $name   = str_replace(' ', '_', strtolower((string) $this->argument('name')));
        $module = $this->option('module');
        $stamp  = date('Y_m_d_His');
        $file   = "{$stamp}_{$name}.php";

        $path = $module
            ? base_path("modules/{$module}/Database/Migrations/{$file}")
            : base_path("database/migrations/{$file}");

        @mkdir(dirname($path), 0755, true);

        $class   = implode('', array_map('ucfirst', explode('_', $name)));
        $table   = preg_replace('/^(create|add|drop)_(.+?)(_table)?$/', '$2', $name);

        file_put_contents($path, <<<PHP
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
PHP);
        $this->success("Migration [{$file}] created.");
        return self::SUCCESS;
    }
}

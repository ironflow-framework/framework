<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Connection;
use Core\Database\Migrations\Migrator;

class MigrateCommand extends Command
{
    protected string $signature   = 'migrate {--path=}';
    protected string $description = 'Run pending database migrations';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $path = $this->option('path') ?? base_path('database/migrations');

        if (!is_dir($path)) {
            // Also look in modules
            $this->runAllModuleMigrations();
            return self::SUCCESS;
        }

        $migrator = new Migrator($this->db);
        $ran      = $migrator->run($path);

        if (empty($ran)) {
            $this->info('Nothing to migrate.');
        } else {
            foreach ($ran as $migration) {
                $this->success("Migrated: {$migration}");
            }
        }

        return self::SUCCESS;
    }

    private function runAllModuleMigrations(): void
    {
        $migrator    = new Migrator($this->db);
        $modulesPath = base_path('modules');

        if (!is_dir($modulesPath)) {
            $this->warn('No migrations directory found.');
            return;
        }

        foreach (glob($modulesPath . '/*/Database/Migrations') ?: [] as $path) {
            $ran = $migrator->run($path);
            foreach ($ran as $m) {
                $this->success("Migrated: {$m}");
            }
        }
    }
}

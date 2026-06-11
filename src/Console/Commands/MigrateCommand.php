<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;
use Ironflow\Database\Connection;
use Ironflow\Database\Migrations\Migrator;

class MigrateCommand extends Command
{
    protected string $signature = 'migrate {--path=}';
    protected string $description = 'Run pending database migrations';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $path = $this->option('path') ?? base_path('database/migrations');

        if (!is_dir($path)) {
            $this->runAllModuleMigrations();
            return self::SUCCESS;
        }

        $migrator = new Migrator($this->db);
        $ran = $migrator->run($path);

        if (empty($ran)) {
            $this->info('Nothing to migrate.');
        } else {
            $this->newLine();
            foreach ($ran as $item) {
                $this->migrationLine($item['file'], $item['ms']);
            }
            $this->newLine();
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

        $any = false;
        foreach (glob($modulesPath . '/*/Database/Migrations') ?: [] as $path) {
            $ran = $migrator->run($path);
            if (!empty($ran) && !$any) {
                $this->newLine();
                $any = true;
            }
            foreach ($ran as $item) {
                $this->migrationLine($item['file'], $item['ms']);
            }
        }

        if ($any) {
            $this->newLine();
        } else {
            $this->info('Nothing to migrate.');
        }
    }
}

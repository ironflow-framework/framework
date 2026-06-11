<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;
use Ironflow\Database\Connection;
use Ironflow\Database\Migrations\Migrator;

class MigrateFreshCommand extends Command
{
    protected string $signature = 'migrate:fresh {--seed}';
    protected string $description = 'Drop all tables and re-run all migrations';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        if (!$this->confirm('This will drop all tables. Are you sure?', false)) {
            return self::SUCCESS;
        }

        $migrator = new Migrator($this->db);
        $path     = base_path('modules');
        $paths    = glob($path . '/*/Database/Migrations') ?: [];

        $this->newLine();

        if (empty($paths)) {
            $migrator->fresh($path);
        } else {
            foreach ($migrator->fresh($paths[0]) as $item) {
                $this->migrationLine($item['file'], $item['ms']);
            }
            for ($i = 1; $i < count($paths); $i++) {
                foreach ($migrator->run($paths[$i]) as $item) {
                    $this->migrationLine($item['file'], $item['ms']);
                }
            }
        }

        $this->newLine();

        if ($this->option('seed')) {
            $this->info('Seeding database...');
        }

        return self::SUCCESS;
    }
}

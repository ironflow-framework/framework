<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;
use Ironflow\Database\Connection;
use Ironflow\Database\Migrations\Migrator;

class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback {--path=}';
    protected string $description = 'Rollback the last batch of migrations';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $path = $this->option('path') ?? base_path('modules');
        $migrator = new Migrator($this->db);

        // Collect all migration paths
        $paths = [];
        foreach (glob($path . '/*/Database/Migrations') ?: [] as $p) {
            $paths[] = $p;
        }
        if (empty($paths)) {
            $paths[] = $path;
        }

        $rolledBack = [];
        foreach ($paths as $p) {
            $rolledBack = array_merge($rolledBack, $migrator->rollback($p));
        }

        if (empty($rolledBack)) {
            $this->info('Nothing to rollback.');
        } else {
            foreach ($rolledBack as $m) {
                $this->warn("Rolled back: {$m}");
            }
        }

        return self::SUCCESS;
    }
}

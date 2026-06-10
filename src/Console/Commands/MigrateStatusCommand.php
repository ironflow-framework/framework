<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Connection;
use Core\Database\Migrations\Migrator;

class MigrateStatusCommand extends Command
{
    protected string $signature   = 'migrate:status';
    protected string $description = 'Show the status of each migration';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $migrator = new Migrator($this->db);
        $path     = base_path('modules');
        $paths    = glob($path . '/*/Database/Migrations') ?: [$path];

        $all = [];
        foreach ($paths as $p) {
            $all = array_merge($all, $migrator->status($p));
        }

        $rows = array_map(fn($s) => [
            $s['ran'] ? '<info>Ran</info>' : '<comment>Pending</comment>',
            $s['migration'],
            $s['batch'] ?? '',
        ], $all);

        $this->table(['Status', 'Migration', 'Batch'], $rows);
        return self::SUCCESS;
    }
}

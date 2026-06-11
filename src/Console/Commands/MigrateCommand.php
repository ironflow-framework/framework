<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;
use Ironflow\Database\Connection;
use Ironflow\Database\Migrations\Migrator;

class MigrateCommand extends Command
{
    protected string $signature   = 'migrate {--path=} {--fresh} {--seed} {--rollback}';
    protected string $description = 'Run pending database migrations';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $migrator = new Migrator($this->db);

        if ($this->option('rollback')) {
            return $this->runRollback($migrator);
        }

        $explicitPath = $this->option('path');

        if ($explicitPath !== null) {
            return $this->runPath($migrator, (string) $explicitPath);
        }

        return $this->runAll($migrator);
    }

    private function runAll(Migrator $migrator): int
    {
        if ($this->option('fresh')) {
            $this->freshAll($migrator);
        }

        $paths   = $this->resolveMigrationPaths();
        $any     = false;

        foreach ($paths as $path) {
            $ran = $migrator->run($path);
            if (!empty($ran)) {
                if (!$any) {
                    $this->newLine();
                    $any = true;
                }
                foreach ($ran as $item) {
                    $this->migrationLine($item['file'], $item['ms']);
                }
            }
        }

        if (!$any) {
            $this->info('Nothing to migrate.');
        } else {
            $this->newLine();
        }

        if ($this->option('seed')) {
            $this->call('db:seed');
        }

        return self::SUCCESS;
    }

    private function runPath(Migrator $migrator, string $path): int
    {
        if (!is_dir($path)) {
            $this->error("Migration path [{$path}] does not exist.");
            return self::FAILURE;
        }

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

    private function runRollback(Migrator $migrator): int
    {
        $paths = $this->resolveMigrationPaths();
        $any   = false;

        foreach ($paths as $path) {
            $rolled = $migrator->rollback($path);
            if (!empty($rolled)) {
                if (!$any) {
                    $this->newLine();
                    $any = true;
                }
                foreach ($rolled as $item) {
                    $this->output->writeln(
                        "  <fg=yellow>ROLLBACK</>  {$item['file']} <fg=gray>({$item['ms']}ms)</>"
                    );
                }
            }
        }

        if (!$any) {
            $this->info('Nothing to roll back.');
        } else {
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function freshAll(Migrator $migrator): void
    {
        $this->warn('Dropping all tables and re-running all migrations...');
        foreach ($this->resolveMigrationPaths() as $path) {
            $migrator->fresh($path);
        }
    }

    /** @return string[] */
    private function resolveMigrationPaths(): array
    {
        return Migrator::discoverPaths(base_path());
    }
}

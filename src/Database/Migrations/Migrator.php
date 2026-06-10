<?php

declare(strict_types=1);

namespace Ironflow\Database\Migrations;

use Ironflow\Database\Connection;
use Ironflow\Database\Schema\Schema;

/**
 * Discovers, runs, and rolls back migrations.
 * Tracks ran migrations in a `migrations` table with batch numbers.
 */
class Migrator
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    public function run(string $path): array
    {
        $pending = $this->getPendingMigrations($path);
        if (empty($pending)) {
            return [];
        }

        $batch = $this->getLastBatch() + 1;
        $ran = [];

        foreach ($pending as $file) {
            $class = $this->classFromFile($file);
            require_once $file;

            $migration = new $class();
            $migration->up();

            $this->db->insert('migrations', [
                'migration' => basename($file, '.php'),
                'batch' => $batch,
                'ran_at' => date('Y-m-d H:i:s'),
            ]);

            $ran[] = basename($file);
        }

        return $ran;
    }

    public function rollback(string $path): array
    {
        $lastBatch = $this->getLastBatch();
        if ($lastBatch === 0) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC',
            [$lastBatch]
        );

        $rolledBack = [];

        foreach ($rows as $row) {
            $file = $path . '/' . $row['migration'] . '.php';
            if (!is_file($file)) {
                continue;
            }

            $class = $this->classFromFile($file);
            require_once $file;

            $migration = new $class();
            $migration->down();

            $this->db->statement('DELETE FROM migrations WHERE migration = ?', [$row['migration']]);
            $rolledBack[] = $row['migration'];
        }

        return $rolledBack;
    }

    public function status(string $path): array
    {
        $ran = array_column(
            $this->db->select('SELECT migration, batch FROM migrations ORDER BY id'),
            null,
            'migration'
        );

        $files = $this->getMigrationFiles($path);
        $status = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $status[] = [
                'migration' => $name,
                'ran' => isset($ran[$name]),
                'batch' => $ran[$name]['batch'] ?? null,
            ];
        }

        return $status;
    }

    public function fresh(string $path): array
    {
        // Drop all tables then re-run migrations
        $sm = $this->db->getSchemaManager();
        $tables = $sm->listTableNames();
        foreach (array_reverse($tables) as $table) {
            if ($table === 'migrations')
                continue;
            Schema::drop($table);
        }
        $this->db->statement('DELETE FROM migrations');

        return $this->run($path);
    }

    private function getPendingMigrations(string $path): array
    {
        $ran = array_column($this->db->select('SELECT migration FROM migrations'), 'migration');
        $files = $this->getMigrationFiles($path);

        return array_filter($files, function ($file) use ($ran) {
            return !in_array(basename($file, '.php'), $ran, true);
        });
    }

    private function getMigrationFiles(string $path): array
    {
        $files = glob($path . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    private function classFromFile(string $file): string
    {
        $name = basename($file, '.php');
        // Convert 2024_01_01_000000_create_posts_table → CreatePostsTable
        $parts = explode('_', $name);
        // Skip timestamp prefix (first 4 parts)
        $classParts = array_slice($parts, 4);
        return implode('', array_map('ucfirst', $classParts));
    }

    private function getLastBatch(): int
    {
        $row = $this->db->selectOne('SELECT MAX(batch) as b FROM migrations');
        return (int) ($row['b'] ?? 0);
    }

    private function ensureTable(): void
    {
        $this->db->statement(
            "CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL DEFAULT 0,
                ran_at DATETIME
            )"
        );
    }
}

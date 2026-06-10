<?php

declare(strict_types=1);

namespace Core\Database\Schema;

use Core\Application;
use Core\Database\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;

/**
 * Schema facade — create, alter, drop tables.
 * Translates Blueprint calls into Doctrine DBAL Schema operations.
 */
class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        self::buildTable($blueprint, false);
    }

    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        self::buildTable($blueprint, true);
    }

    public static function drop(string $table): void
    {
        $sm = self::connection()->getSchemaManager();
        if ($sm->tablesExist([$table])) {
            $sm->dropTable($table);
        }
    }

    public static function dropIfExists(string $table): void
    {
        self::drop($table);
    }

    public static function hasTable(string $table): bool
    {
        return self::connection()->getSchemaManager()->tablesExist([$table]);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $cols = self::connection()->getSchemaManager()->listTableColumns($table);
        return isset($cols[strtolower($column)]);
    }

    private static function buildTable(Blueprint $blueprint, bool $alter): void
    {
        $conn     = self::connection();
        $platform = $conn->getPlatform();
        $sm       = $conn->getSchemaManager();

        if ($alter && $sm->tablesExist([$blueprint->getTableName()])) {
            // Build ALTER statements
            foreach ($blueprint->getColumns() as $col) {
                if ($col instanceof ColumnDefinition) {
                    $opts = $col->getOptions();
                    $conn->statement(
                        self::buildAddColumnSql($blueprint->getTableName(), $col->name, $col->type, $opts, $platform)
                    );
                }
            }
            return;
        }

        // CREATE TABLE
        $cols = [];
        $primaryKey = null;

        foreach ($blueprint->getColumns() as $col) {
            if (is_array($col)) {
                // id() shorthand
                $opts     = $col['options'];
                $autoInc  = $opts['autoincrement'] ?? false;
                $nullable = !($opts['notnull'] ?? true);

                $typeSql  = self::mapTypeSql($col['type'], $opts, $platform);
                $colSql   = "`{$col['name']}` {$typeSql}";
                if ($autoInc) {
                    $colSql .= ' NOT NULL AUTO_INCREMENT';
                } elseif ($nullable) {
                    $colSql .= ' NULL';
                } else {
                    $colSql .= ' NOT NULL';
                    if (array_key_exists('default', $opts)) {
                        $colSql .= ' DEFAULT ' . self::quoteDefault($opts['default']);
                    }
                }
                $cols[] = $colSql;
                if ($opts['autoincrement'] ?? false) {
                    $primaryKey = $col['name'];
                }
            } elseif ($col instanceof ColumnDefinition) {
                $opts    = $col->getOptions();
                $typeSql = self::mapTypeSql($col->type, $opts, $platform);
                $colSql  = "`{$col->name}` {$typeSql}";
                $nullable = !($opts['notnull'] ?? true);
                if ($nullable) {
                    $colSql .= ' NULL';
                } else {
                    $colSql .= ' NOT NULL';
                }
                if (array_key_exists('default', $opts)) {
                    $colSql .= ' DEFAULT ' . self::quoteDefault($opts['default']);
                }
                $cols[] = $colSql;
            }
        }

        // Primary key
        foreach ($blueprint->getIndices() as $idx) {
            if ($idx['type'] === 'primary') {
                $cols[] = 'PRIMARY KEY (`' . implode('`, `', $idx['columns']) . '`)';
            }
        }

        // Unique / Index
        foreach ($blueprint->getIndices() as $idx) {
            if ($idx['type'] === 'unique') {
                $cols[] = 'UNIQUE KEY (`' . implode('`, `', $idx['columns']) . '`)';
            } elseif ($idx['type'] === 'index') {
                $cols[] = 'INDEX (`' . implode('`, `', $idx['columns']) . '`)';
            }
        }

        // Foreign keys
        foreach ($blueprint->getForeigns() as $fk) {
            $fkSql = "FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['table']}` (`{$fk['ref_column']}`)";
            if ($fk['on_delete']) {
                $fkSql .= " ON DELETE {$fk['on_delete']}";
            }
            $cols[] = $fkSql;
        }

        $driver = strtolower(class_basename(get_class($platform)));
        $isSqlite = str_contains($driver, 'sqlite');

        $engine  = $isSqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $backtick = $isSqlite ? '"' : '`';

        // For SQLite, simplify
        if ($isSqlite) {
            $cols = array_filter($cols, fn($c) => !str_starts_with(trim($c), 'FOREIGN KEY'));
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$blueprint->getTableName()}` (\n  "
            . implode(",\n  ", $cols)
            . "\n){$engine}";

        // SQLite doesn't like backticks
        if ($isSqlite) {
            $sql = str_replace('`', '"', $sql);
        }

        $conn->statement($sql);
    }

    private static function mapTypeSql(string $type, array $opts, mixed $platform): string
    {
        $driver = strtolower(class_basename(get_class($platform)));
        $isSqlite = str_contains($driver, 'sqlite');

        return match ($type) {
            'bigint'           => $isSqlite ? 'INTEGER' : 'BIGINT UNSIGNED',
            'integer'          => $isSqlite ? 'INTEGER' : 'INT',
            'string'           => 'VARCHAR(' . ($opts['length'] ?? 255) . ')',
            'text'             => 'TEXT',
            'boolean'          => $isSqlite ? 'INTEGER' : 'TINYINT(1)',
            'float', 'decimal' => 'DECIMAL(' . ($opts['precision'] ?? 8) . ',' . ($opts['scale'] ?? 2) . ')',
            'json'             => $isSqlite ? 'TEXT' : 'JSON',
            'datetime_mutable', 'datetime' => 'DATETIME',
            'date_mutable', 'date'         => 'DATE',
            default            => strtoupper($type),
        };
    }

    private static function quoteDefault(mixed $value): string
    {
        if ($value === null) return 'NULL';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . addslashes((string) $value) . "'";
    }

    private static function buildAddColumnSql(string $table, string $col, string $type, array $opts, mixed $platform): string
    {
        $typeSql = self::mapTypeSql($type, $opts, $platform);
        $nullable = !($opts['notnull'] ?? true);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        return "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$typeSql} {$null}";
    }

    private static function connection(): Connection
    {
        return Application::getInstance()->getContainer()->make(Connection::class);
    }
}

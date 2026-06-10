<?php

declare(strict_types=1);

namespace Ironflow\Database\Relations;

use Ironflow\Database\Connection;
use Ironflow\Database\Model;
use Ironflow\Database\ModelQueryBuilder;
use Ironflow\Support\Collection;

/**
 * Many-to-many relation via a pivot table.
 * Post belongsToMany Tag via post_tag.
 */
class BelongsToMany extends Relation
{
    private array $pivotColumns = [];

    public function __construct(
        Connection $connection,
        Model $related,
        private readonly string $pivotTable,
        private readonly string $foreignPivotKey,
        private readonly string $relatedPivotKey,
        mixed $parentKeyValue
    ) {
        parent::__construct($connection, $related, $foreignPivotKey, 'id', $parentKeyValue);
    }

    public function withPivot(string ...$columns): static
    {
        $this->pivotColumns = $columns;
        return $this;
    }

    public function getResults(): Collection
    {
        if ($this->parentKeyValue === null) {
            return new Collection();
        }

        $table = $this->related->getTableName();
        $class = get_class($this->related);
        $pivotCols = $this->pivotColumns ? ', ' . implode(', ', array_map(
            fn($c) => "{$this->pivotTable}.{$c} as pivot_{$c}",
            $this->pivotColumns
        )) : '';

        $sql = "SELECT {$table}.*{$pivotCols} FROM {$table} "
            . "INNER JOIN {$this->pivotTable} ON {$this->pivotTable}.{$this->relatedPivotKey} = {$table}.id "
            . "WHERE {$this->pivotTable}.{$this->foreignPivotKey} = ?";

        $rows = $this->connection->select($sql, [$this->parentKeyValue]);

        return $this->hydrateModels($rows, $class);
    }

    public function eagerLoad(Collection $models, ?callable $constraint): Collection
    {
        $keys = $models->pluck('id')->filter()->unique()->toArray();
        if (empty($keys)) {
            return new Collection();
        }

        $table = $this->related->getTableName();
        $class = get_class($this->related);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $sql = "SELECT {$table}.*, {$this->pivotTable}.{$this->foreignPivotKey} as _pivot_parent "
            . "FROM {$table} "
            . "INNER JOIN {$this->pivotTable} ON {$this->pivotTable}.{$this->relatedPivotKey} = {$table}.id "
            . "WHERE {$this->pivotTable}.{$this->foreignPivotKey} IN ({$placeholders})";

        $rows = $this->connection->select($sql, $keys);
        return $this->hydrateModels($rows, $class);
    }

    public function match(Collection $models, Collection $results, string $relation): void
    {
        $grouped = [];
        foreach ($results as $result) {
            $key = $result->_pivot_parent ?? null;
            if ($key !== null) {
                $grouped[$key][] = $result;
            }
        }

        foreach ($models as $model) {
            $model->setRelation($relation, new Collection($grouped[$model->id] ?? []));
        }
    }

    // ─────────────────────── Pivot operations ────────────────────────

    public function attach(int|array $ids, array $pivot = []): void
    {
        $ids = (array) $ids;
        foreach ($ids as $id) {
            $data = array_merge([$this->foreignPivotKey => $this->parentKeyValue, $this->relatedPivotKey => $id], $pivot);
            $this->connection->insert($this->pivotTable, $data);
        }
    }

    public function detach(int|array $ids = null): void
    {
        $sql = "DELETE FROM {$this->pivotTable} WHERE {$this->foreignPivotKey} = ?";
        $bindings = [$this->parentKeyValue];

        if ($ids !== null) {
            $ids = (array) $ids;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND {$this->relatedPivotKey} IN ({$placeholders})";
            $bindings = array_merge($bindings, $ids);
        }

        $this->connection->statement($sql, $bindings);
    }

    public function sync(array $ids): void
    {
        $this->detach();
        $this->attach($ids);
    }

    public function toggle(array $ids): void
    {
        $current = $this->getResults()->pluck('id')->toArray();
        $attach = array_diff($ids, $current);
        $detach = array_intersect($current, $ids);
        $this->attach($attach);
        $this->detach($detach);
    }

    private function hydrateModels(array $rows, string $class): Collection
    {
        $models = [];
        foreach ($rows as $row) {
            $model = new $class();
            $model->setRawAttributes($row);
            $model->setOriginal($row);
            $model->setExists(true);
            $models[] = $model;
        }
        return new Collection($models);
    }
}

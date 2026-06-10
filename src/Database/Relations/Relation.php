<?php

declare(strict_types=1);

namespace Core\Database\Relations;

use Core\Database\Connection;
use Core\Database\Model;
use Core\Support\Collection;

/**
 * Base class for all ORM relations.
 */
abstract class Relation
{
    public function __construct(
        protected Connection $connection,
        protected Model      $related,
        protected string     $foreignKey,
        protected string     $localKey,
        protected mixed      $parentKeyValue
    ) {}

    abstract public function getResults(): mixed;

    /**
     * Eager-load this relation for a collection of parent models.
     * Returns a Collection of related models.
     */
    abstract public function eagerLoad(Collection $models, ?callable $constraint): Collection;

    /**
     * After eager loading, match loaded results back onto parent models.
     */
    abstract public function match(Collection $models, Collection $results, string $relation): void;

    /**
     * Load the count of this relation and set it as an attribute on each model.
     */
    public function eagerLoadCount(Collection $models, string $countKey): void
    {
        $table = $this->related->getTableName();
        $ids   = $models->pluck($this->localKey)->filter()->toArray();

        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT {$this->foreignKey}, COUNT(*) as cnt FROM {$table} WHERE {$this->foreignKey} IN ({$placeholders}) GROUP BY {$this->foreignKey}";
        $rows = $this->connection->select($sql, $ids);

        $map = [];
        foreach ($rows as $row) {
            $map[$row[$this->foreignKey]] = (int) $row['cnt'];
        }

        foreach ($models as $model) {
            $parentId = $model->{$this->localKey};
            $model->setRawAttribute($countKey, $map[$parentId] ?? 0);
        }
    }

    public function getRelatedModel(): Model
    {
        return $this->related;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    protected function getParentKeys(Collection $models): array
    {
        return $models->pluck($this->localKey)->filter()->unique()->toArray();
    }
}

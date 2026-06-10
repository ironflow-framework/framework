<?php

declare(strict_types=1);

namespace Ironflow\Database\Relations;

use Ironflow\Database\Connection;
use Ironflow\Database\Model;
use Ironflow\Support\Collection;

/**
 * Has-many-through: Country hasManyThrough Post through User.
 */
class HasManyThrough extends Relation
{
    public function __construct(
        Connection $connection,
        Model $related,
        private readonly Model $through,
        string $firstKey,
        private readonly string $secondKey,
        string $localKey,
        private readonly string $secondLocalKey,
        mixed $parentKeyValue
    ) {
        parent::__construct($connection, $related, $firstKey, $localKey, $parentKeyValue);
    }

    public function getResults(): Collection
    {
        $relatedTable = $this->related->getTableName();
        $throughTable = $this->through->getTableName();
        $class = get_class($this->related);

        $sql = "SELECT {$relatedTable}.* FROM {$relatedTable} "
            . "INNER JOIN {$throughTable} ON {$throughTable}.{$this->secondLocalKey} = {$relatedTable}.{$this->secondKey} "
            . "WHERE {$throughTable}.{$this->foreignKey} = ?";

        $rows = $this->connection->select($sql, [$this->parentKeyValue]);

        return $this->hydrateModels($rows, $class);
    }

    public function eagerLoad(Collection $models, ?callable $constraint): Collection
    {
        $keys = $this->getParentKeys($models);
        if (empty($keys)) {
            return new Collection();
        }

        $relatedTable = $this->related->getTableName();
        $throughTable = $this->through->getTableName();
        $class = get_class($this->related);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $sql = "SELECT {$relatedTable}.*, {$throughTable}.{$this->foreignKey} as _through_parent "
            . "FROM {$relatedTable} "
            . "INNER JOIN {$throughTable} ON {$throughTable}.{$this->secondLocalKey} = {$relatedTable}.{$this->secondKey} "
            . "WHERE {$throughTable}.{$this->foreignKey} IN ({$placeholders})";

        $rows = $this->connection->select($sql, $keys);
        return $this->hydrateModels($rows, $class);
    }

    public function match(Collection $models, Collection $results, string $relation): void
    {
        $grouped = [];
        foreach ($results as $result) {
            $key = $result->_through_parent ?? null;
            if ($key !== null) {
                $grouped[$key][] = $result;
            }
        }

        foreach ($models as $model) {
            $model->setRelation($relation, new Collection($grouped[$model->{$this->localKey}] ?? []));
        }
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

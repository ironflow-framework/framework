<?php

declare(strict_types=1);

namespace Core\Database\Relations;

use Core\Database\ModelQueryBuilder;
use Core\Database\Model;
use Core\Support\Collection;

/**
 * Inverse of HasOne/HasMany: a Comment belongsTo Post.
 */
class BelongsTo extends Relation
{
    public function __construct(
        \Core\Database\Connection $connection,
        Model $related,
        string $foreignKey,   // e.g. post_id (on the child model)
        string $ownerKey,     // e.g. id (on the parent model)
        mixed $foreignKeyValue // the actual value of post_id on this instance
    ) {
        parent::__construct($connection, $related, $foreignKey, $ownerKey, $foreignKeyValue);
    }

    public function getResults(): ?Model
    {
        if ($this->parentKeyValue === null) {
            return null;
        }

        $class = get_class($this->related);
        return (new ModelQueryBuilder($this->connection, $this->related->getTableName(), $class))
            ->where($this->localKey, $this->parentKeyValue)
            ->first();
    }

    public function eagerLoad(Collection $models, ?callable $constraint): Collection
    {
        // For BelongsTo, foreignKey is on the child (models), ownerKey on the parent
        $keys = $models->pluck($this->foreignKey)->filter()->unique()->toArray();
        if (empty($keys)) {
            return new Collection();
        }

        $class = get_class($this->related);
        $qb    = (new ModelQueryBuilder($this->connection, $this->related->getTableName(), $class))
            ->whereIn($this->localKey, $keys);

        if ($constraint !== null) {
            $constraint($qb);
        }

        return $qb->get();
    }

    public function match(Collection $models, Collection $results, string $relation): void
    {
        $map = [];
        foreach ($results as $result) {
            $map[$result->{$this->localKey}] = $result;
        }

        foreach ($models as $model) {
            $fk = $model->{$this->foreignKey};
            $model->setRelation($relation, $map[$fk] ?? null);
        }
    }
}

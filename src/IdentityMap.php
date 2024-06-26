<?php

namespace Brahmic\Filler;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

class IdentityMap extends Collection
{
    protected array $trackedRelations = [];

    /**
     * @param Model $model
     * @param array|null $data
     * @return string|null
     */
    public static function resolveHashName(Model $model, ?array $data = null): ?string
    {
        if($key = static::resolveKey($model, $data)){
            return get_class($model) . '#' . $key;
        }

        return null;
    }

    /**
     * @param Model $model
     * @param array|null $data
     * @return string|null
     */
    protected static function resolveKey(Model $model, ?array $data = null): ?string
    {
        $primaryKeyName = Resolver::resolveKeyName($model);

        if ($data && isset($data[$primaryKeyName])) {
            return $data[$primaryKeyName];
        }

        return $model->getKey();
    }

    /**
     * @param EloquentCollection|Model|null $relation
     * @return array|string|null
     */
    public function remember(Model|EloquentCollection|null $relation): array|string|null
    {
        if (is_null($relation)) {
            return null;
        }

        if ($relation instanceof Pivot) {
            return null;
        }

        if ($relation instanceof EloquentCollection) {
            return $relation->map(function (Model $model) {
                return $this->remember($model);
            })->toArray();
        }

        foreach ($relation->getRelations() as $key => $nestedRelation) {
            if ($this->isTrackedRelation(static::resolveRelationHashName($relation, $key))) {
                continue;
            }

            $this->remember($nestedRelation);
            $this->markTracked(static::resolveRelationHashName($relation, $key));
        }

        $hashName = static::resolveHashName($relation);
        $this[$hashName] = $relation;

        return $hashName;
    }

    public function isTrackedRelation($key): bool
    {
        return in_array($key, $this->trackedRelations);
    }

    public static function resolveRelationHashName(Model $model, $relationName): string
    {
        return static::resolveHashName($model) . '#' . $relationName;
    }

    public function markTracked($key)
    {
        return $this->trackedRelations[] = $key;
    }

    public function clear()
    {
        $this->trackedRelations = [];
        $this->items = [];
    }
}

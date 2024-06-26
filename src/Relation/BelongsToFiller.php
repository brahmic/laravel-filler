<?php

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class BelongsToFiller extends RelationFiller
{
    /**
     * @param Model $model
     * @param Relation|BelongsTo $relation
     * @param array|null $data
     * @param string $relationName
     */
    function fill(Model $model, BelongsTo|Relation $relation, ?array $data, string $relationName): void
    {
        $related = $data ? $this->resolver->find($relation->getRelated(), $data) : null;

        $this->fillRelationField($model, $relation, $related);

        $model->setRelation(Str::snake($relationName), $related);
    }

    protected function fillRelationField(Model $model, BelongsTo $relation, ?Model $related): void
    {
        $model->{$relation->getForeignKeyName()} = $related?->{$relation->getOwnerKeyName()};
    }
}

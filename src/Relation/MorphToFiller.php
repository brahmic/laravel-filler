<?php

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class MorphToFiller extends RelationFiller
{
    /**
     * @param Model $model
     * @param Relation|MorphTo $relation
     * @param array|null $data
     * @param string $relationName
     * @throws \Exception
     */
    function fill(Model $model, Relation $relation, ?array $data, string $relationName): void
    {
        $related = $this->filler->fill(get_class($relation->getRelated()), $data);

        $this->fillRelationField($model, $relation, $related);

        $model->setRelation(Str::snake($relationName), $related);
    }

    protected function fillRelationField(Model $model, BelongsTo $relation, ?Model $related): void
    {
        $model->{$relation->getForeignKeyName()} = $related ? $related->{$relation->getOwnerKeyName()} : null;
    }
}

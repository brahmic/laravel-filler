<?php

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class HasOneFiller extends RelationFiller
{
    /**
     * @param Model $model
     * @param Relation|HasOne $relation
     * @param array|null $data
     * @param string $relationName
     * @throws \Exception
     */
    public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void
    {
        /** @var ?Model $existsModel */
        $existsModel = $this->resolver->loadRelation($model, $relationName);

        /** @var ?Model $relatedModel */
        $relatedModel = $this->filler->fill(get_class($relation->getRelated()), $data);

        if (!is_null($existsModel) && !$existsModel->is($relatedModel)) {
            $this->uow->destroy($existsModel);
        }

        if (!is_null($relatedModel)) {
            $this->setRelationField($relatedModel, $relation, $model);
            $this->uow->persist($relatedModel);
        }

        $model->setRelation(Str::snake($relationName), $relatedModel);
    }

    protected function setRelationField(Model $model, HasOne $relation, Model $related): void
    {
        $model->{$relation->getForeignKeyName()} = $related ? $related->{$relation->getLocalKeyName()} : null;
    }
}

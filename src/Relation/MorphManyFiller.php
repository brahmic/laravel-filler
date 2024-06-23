<?php

namespace Brahmic\Filler\Relation;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class MorphManyFiller extends RelationFiller
{
    /**
     * @param Model $model
     * @param Relation|MorphMany $relation
     * @param array|null $data
     * @param string $relationName
     */
    public function fill(Model $model, MorphMany|Relation $relation, ?array $data, string $relationName): void
    {
        if (is_null($data)) {
            return;
        }

        $existsModels = $this->resolver->loadRelation($model, $relationName);

        $relatedModels = $relation->getQuery()->getModel()->newCollection()
            ->concat(array_map(function (array $modelData) use ($relation) {
                return $this->filler->fill(get_class($relation->getRelated()), $modelData);
            }, $data));

        $existsModels->filter(function (Model $existsModel) use ($relatedModels): bool {
            return $relatedModels->filter(function (Model $relatedModel) use ($existsModel): bool {
                return $existsModel->is($relatedModel);
            })->isEmpty();
        })->each(function (Model $model) use ($relation): void {
            $this->uow->destroy($model);
        });

        $relatedModels->each(function (Model $related) use ($relation, $model, $relationName): void {
            $this->setRelationField($model, $relation, $related);
            $this->uow->persist($related);
        });

        $model->setRelation(Str::snake($relationName), $relatedModels);
    }

    protected function setRelationField(Model $model, MorphMany $relation, Model $related): void
    {
        $related->{$relation->getForeignKeyName()} = $model->{$relation->getLocalKeyName()};
        $related->{$relation->getMorphType()} = $model->getMorphClass();
    }
}

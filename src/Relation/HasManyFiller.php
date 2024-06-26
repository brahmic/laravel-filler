<?php

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Exceptions\FillerMappingException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class HasManyFiller extends RelationFiller
{

    /**
     * @param Model $model
     * @param Relation|HasMany $relation
     * @param array|null $data
     * @param string $relationName
     */
    public function fill(Model $model, HasMany|Relation $relation, ?array $data, string $relationName): void
    {
        if (is_null($data)) {
            return;
        }

        if (Arr::isAssoc($data)) {
            throw new FillerMappingException(
                sprintf('Данные для отношения %s::%s должны быть списком ассоциативных массивов', get_class($model),
                    $relationName)
            );
        }

        /** @var Collection $existsModels */
        $existsModels = $this->resolver->loadRelation($model, $relationName);

        $relatedModels = $relation->getQuery()->getModel()->newCollection()
            ->concat(array_map(function (array $modelData) use ($relation, $model, $relationName, $data) {

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

    protected function setRelationField(Model $model, HasMany $relation, Model $related): void
    {
        $related->{$relation->getForeignKeyName()} = $model->{$relation->getLocalKeyName()};
    }
}

<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Exceptions\FillerMappingException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class HasManyFiller extends HasOneOrManyFiller
{

    /**
     * Заполняет отношение HasMany данными
     *
     * @param Model $model Родительская модель, содержащая отношение
     * @param HasMany|Relation $relation Объект отношения
     * @param array|null $data Данные для заполнения отношения
     * @param string $relationName Имя отношения
     * @throws FillerMappingException Если формат данных для отношения HasMany неверный
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
            ->concat(array_map(fn (array $modelData) => $this->filler->fill(get_class($relation->getRelated()), $modelData), $data));

        $existsModels->filter(fn (Model $existsModel) => $relatedModels->filter(
            fn (Model $relatedModel) => $existsModel->is($relatedModel)
        )->isEmpty()
        )->each(fn (Model $model) => $this->uow->destroy($model));

        $relatedModels->each(function (Model $related) use ($relation, $model, $relationName): void {
            $this->setRelationField($model, $relation, $related);
            $this->uow->persist($related);
        });

        $model->setRelation(Str::snake($relationName), $relatedModels);
    }

    /**
     * Устанавливает значение внешнего ключа в связанной модели для отношения HasMany
     *
     * @param Model $model Родительская модель
     * @param HasMany $relation Объект отношения
     * @param Model $related Связанная модель
     */
    protected function setRelationField(Model $model, HasMany $relation, Model $related): void
    {
        $related->{$relation->getForeignKeyName()} = $model->{$relation->getLocalKeyName()};
    }
}

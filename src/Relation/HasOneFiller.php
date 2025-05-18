<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class HasOneFiller extends HasOneOrManyFiller
{
    /**
     * Заполняет отношение HasOne данными
     *
     * @param Model $model Родительская модель, содержащая отношение
     * @param HasOne|Relation $relation Объект отношения
     * @param array|null $data Данные для заполнения отношения
     * @param string $relationName Имя отношения
     * @throws \Exception Если произошла ошибка при обработке отношения
     */
    public function fill(Model $model, HasOne|Relation $relation, ?array $data, string $relationName): void
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

    /**
     * Устанавливает значение внешнего ключа в модели для связи HasOne
     *
     * @param Model $model Связанная модель
     * @param HasOneOrMany $relation Объект отношения
     * @param Model $related Родительская модель
     */
    protected function setRelationField(Model $model, HasOneOrMany $relation, Model $related): void
    {
        $model->{$relation->getForeignKeyName()} = $related?->{$relation->getLocalKeyName()};
    }
}

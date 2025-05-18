<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Базовый класс для филлеров полиморфных отношений MorphOne и MorphMany
 */
abstract class MorphOneOrManyFiller extends RelationFiller
{
    /**
     * Устанавливает значения внешнего ключа и типа в модели для полиморфных связей
     *
     * @param Model $model Связанная модель
     * @param MorphOneOrMany $relation Объект отношения
     * @param Model $related Родительская модель
     */
    protected function setRelationField(Model $model, MorphOneOrMany $relation, Model $related): void
    {
        // Устанавливаем внешний ключ и тип морфа
        $model->{$relation->getForeignKeyName()} = $related->{$relation->getLocalKeyName()};
        $model->{$relation->getMorphType()} = $related->getMorphClass();
    }
}
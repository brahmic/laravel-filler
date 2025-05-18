<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Базовый класс для филлеров отношений HasOne и HasMany
 */
abstract class HasOneOrManyFiller extends RelationFiller
{
    /**
     * Устанавливает значение внешнего ключа в модели для связей HasOne и HasMany
     *
     * @param Model $model Связанная модель
     * @param HasOneOrMany $relation Объект отношения
     * @param Model $related Родительская модель
     */
    protected function setRelationField(Model $model, HasOneOrMany $relation, Model $related): void
    {
        $model->{$relation->getForeignKeyName()} = $related->{$relation->getLocalKeyName()};
    }
}
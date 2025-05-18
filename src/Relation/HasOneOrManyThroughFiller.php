<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Базовый класс для филлеров отношений HasOneThrough и HasManyThrough
 */
abstract class HasOneOrManyThroughFiller extends RelationFiller
{
    /**
     * Устанавливает значения ключей для связей через промежуточную таблицу
     *
     * @param Model $parent Родительская модель
     * @param HasOneOrManyThrough $relation Объект отношения
     * @param Model $throughModel Промежуточная модель
     * @param Model $farModel Дальняя модель
     */
    protected function setRelationFields(Model $parent, HasOneOrManyThrough $relation, Model $throughModel, Model $farModel): void
    {
        // Получаем имена ключей из отношения
        $firstKey = $relation->getFirstKeyName();
        $secondKey = $relation->getForeignKeyName();
        $localKey = $relation->getLocalKeyName();
        
        // Устанавливаем значение внешнего ключа для промежуточной модели
        if (!is_null($throughModel) && !is_null($parent)) {
            $throughModel->{$firstKey} = $parent->{$localKey};
            $this->uow->persist($throughModel);
        }
        
        // Устанавливаем значение внешнего ключа для дальней модели
        if (!is_null($farModel) && !is_null($throughModel)) {
            $farModel->{$secondKey} = $throughModel->{$relation->getSecondLocalKeyName()};
            $this->uow->persist($farModel);
        }
    }
}
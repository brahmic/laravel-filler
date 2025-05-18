<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Филлер для отношений HasOneThrough
 */
class HasOneThroughFiller extends HasOneOrManyThroughFiller
{
    /**
     * Заполняет отношение HasOneThrough данными
     *
     * @param Model $model Родительская модель, содержащая отношение
     * @param HasOneThrough|Relation $relation Объект отношения
     * @param array|null $data Данные для заполнения отношения
     * @param string $relationName Имя отношения
     */
    public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void
    {
        if (is_null($data)) {
            return;
        }

        /** @var HasOneThrough $relation */
        $existsModel = $this->resolver->loadRelation($model, $relationName);
        
        // Получаем информацию о моделях
        $throughModel = $relation->getParent();
        $farModel = $relation->getRelated();
        
        // Определяем класс промежуточной модели
        $throughClass = get_class($throughModel);
        
        // Загружаем или создаем промежуточную модель, если она указана в данных
        $throughInstance = null;
        if (isset($data['through']) && is_array($data['through'])) {
            $throughInstance = $this->filler->fill($throughClass, $data['through']);
        }
        
        // Если прямо не указана промежуточная модель, пытаемся найти по внешнему ключу
        if (is_null($throughInstance) && isset($data[$relation->getFirstKeyName()])) {
            $throughInstance = $this->resolver->resolve($throughClass, [
                $relation->getLocalKeyName() => $data[$relation->getFirstKeyName()]
            ]);
        }
        
        // Заполняем дальнюю модель
        $relatedModel = $this->filler->fill(get_class($farModel), $data);
        
        // Если у нас есть и промежуточная, и дальняя модель, устанавливаем связи
        if (!is_null($relatedModel) && !is_null($throughInstance)) {
            $this->setRelationFields($model, $relation, $throughInstance, $relatedModel);
        }
        
        // Удаляем существующую модель, если она отличается от новой
        if (!is_null($existsModel) && !$existsModel->is($relatedModel)) {
            $this->uow->destroy($existsModel);
        }
        
        // Сохраняем промежуточную и дальнюю модели
        if (!is_null($throughInstance)) {
            $this->uow->persist($throughInstance);
        }
        
        if (!is_null($relatedModel)) {
            $this->uow->persist($relatedModel);
        }
        
        // Устанавливаем отношение в родительскую модель
        $model->setRelation(Str::snake($relationName), $relatedModel);
    }
}
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Exceptions\FillerMappingException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Филлер для отношений HasManyThrough
 */
class HasManyThroughFiller extends HasOneOrManyThroughFiller
{
    /**
     * Заполняет отношение HasManyThrough данными
     *
     * @param Model $model Родительская модель, содержащая отношение
     * @param HasManyThrough|Relation $relation Объект отношения
     * @param array|null $data Данные для заполнения отношения
     * @param string $relationName Имя отношения
     * @throws FillerMappingException Если формат данных для отношения HasManyThrough неверный
     */
    public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void
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

        /** @var HasManyThrough $relation */
        $existsCollection = $this->resolver->loadRelation($model, $relationName);
        
        // Получаем информацию о моделях
        $throughModel = $relation->getParent();
        $farModel = $relation->getRelated();
        
        // Определяем класс промежуточной модели
        $throughClass = get_class($throughModel);
        
        // Создаем коллекцию для новых моделей
        $collection = new Collection();
        
        // Для каждого элемента данных создаем модель и устанавливаем связи
        foreach ($data as $item) {
            // Загружаем или создаем промежуточную модель, если она указана в данных
            $throughInstance = null;
            if (isset($item['through']) && is_array($item['through'])) {
                $throughInstance = $this->filler->fill($throughClass, $item['through']);
            }
            
            // Если прямо не указана промежуточная модель, пытаемся найти по внешнему ключу
            if (is_null($throughInstance) && isset($item[$relation->getFirstKeyName()])) {
                $throughInstance = $this->resolver->resolve($throughClass, [
                    $relation->getLocalKeyName() => $item[$relation->getFirstKeyName()]
                ]);
            }
            
            // Заполняем дальнюю модель
            $relatedModel = $this->filler->fill(get_class($farModel), $item);
            
            if (!is_null($relatedModel)) {
                $collection->push($relatedModel);
                
                // Если у нас есть и промежуточная, и дальняя модель, устанавливаем связи
                if (!is_null($throughInstance)) {
                    $this->setRelationFields($model, $relation, $throughInstance, $relatedModel);
                    $this->uow->persist($throughInstance);
                }
                
                $this->uow->persist($relatedModel);
            }
        }
        
        // Удаляем модели, которых больше нет в коллекции
        if (!is_null($existsCollection)) {
            foreach ($existsCollection as $existModel) {
                if (!$collection->contains(fn (Model $model) => $model->is($existModel))) {
                    $this->uow->destroy($existModel);
                }
            }
        }
        
        // Устанавливаем отношение в родительскую модель
        $model->setRelation(Str::snake($relationName), $collection);
    }
}
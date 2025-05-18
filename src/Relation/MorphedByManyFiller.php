<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Exceptions\FillerMappingException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Филлер для отношений MorphedByMany
 */
class MorphedByManyFiller extends RelationFiller
{
    /**
     * Заполняет отношение MorphedByMany данными
     *
     * @param Model $model Родительская модель, содержащая отношение
     * @param MorphedByMany|Relation $relation Объект отношения
     * @param array|null $data Данные для заполнения отношения
     * @param string $relationName Имя отношения
     * @throws FillerMappingException Если формат данных для отношения MorphedByMany неверный
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

        /** @var MorphedByMany $relation */
        $existsCollection = $this->resolver->loadRelation($model, $relationName);
        
        // Получить информацию о полиморфной таблице связей
        $morphType = $relation->getMorphType();
        $morphClass = $relation->getMorphClass();
        
        // Создать коллекцию для новых моделей
        $collection = new Collection();
        
        // Обработать данные
        foreach ($data as $item) {
            $relatedModel = $this->filler->fill(get_class($relation->getRelated()), $item);
            if (!is_null($relatedModel)) {
                $collection->push($relatedModel);
                
                // Логика установки pivot-данных
                $pivotData = [];
                if (isset($item['pivot']) && is_array($item['pivot'])) {
                    $pivotData = $item['pivot'];
                }
                
                // Добавляем морфный тип и идентификатор, если они не указаны
                if (!isset($pivotData[$morphType])) {
                    $pivotData[$morphType] = $morphClass;
                }
                
                // Сохраняем или обновляем связь
                $relation->attach($relatedModel, $pivotData);
                
                // Сохраняем модель
                $this->uow->persist($relatedModel);
            }
        }
        
        // Обработка удаления несуществующих моделей
        if (!is_null($existsCollection)) {
            foreach ($existsCollection as $existModel) {
                if (!$collection->contains(fn (Model $model) => $model->is($existModel))) {
                    $relation->detach($existModel);
                }
            }
        }
        
        // Установить отношение в родительскую модель
        $model->setRelation(Str::snake($relationName), $collection);
    }
}
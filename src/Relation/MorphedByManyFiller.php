<?php

namespace Brahmic\Filler\Relation;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Филлер для отношений MorphedByMany
 * 
 * MorphedByMany - это "обратная" сторона MorphToMany отношения,
 * с некоторыми отличиями в настройке полей
 */
class MorphedByManyFiller extends MorphToManyFiller
{
    /**
     * @param Model $model
     * @param Relation|MorphToMany $relation
     * @param array|null $data
     * @param string $relationName
     */
    public function fill(Model $model, MorphToMany|Relation $relation, ?array $data, string $relationName): void
    {
        // If no data provided, just do nothing, because of this is "to many" relation,
        // and we cant set this relation to null.
        // Todo: should exception be thrown?
        if (is_null($data)) {
            return;
        }

        $relatedCollection = collect($data)->map(function ($relatedData) use ($relation, $model) : ?Model {
            // Сначала пытаемся найти существующую модель
            $relatedModel = $this->resolver->findInDataBase($relation->getRelated(), $relatedData);
            
            // Если модель не найдена, создаем новую
            if (!$relatedModel) {
                $relatedModel = $this->filler->fill(get_class($relation->getRelated()), $relatedData);
            }
            
            return $relatedModel ? tap($relatedModel, function (Model $relatedModel) use ($model, $relation, $relatedData) {
                // Генерируем UUID для pivot-таблицы, если используется схема с id
                $pivotData = array_merge(Arr::get($relatedData, 'pivot', []), [
                    'id' => (string) \Illuminate\Support\Str::uuid(), // Генерируем UUID для pivot-записи
                    // Для MorphedByMany морфный тип относится к связанной модели
                    $relation->getMorphType()           => $relation->getMorphClass(),
                    // В MorphedByMany ключи инвертированы относительно MorphToMany
                    $relation->getForeignPivotKeyName() => $relatedModel->{$relation->getRelatedKeyName()},
                    $relation->getRelatedPivotKeyName() => $model->{$relation->getParentKeyName()},
                ]);
                
                $relatedModel->setRelation('pivot', $relation->newPivot(
                    $pivotData, $model->exists
                ));
            }) : null;
        });

        // Filter out null values and build authentic collection for model.
        $relatedCollection = $relation->getQuery()->getModel()->newCollection(
            $relatedCollection->filter()->all()
        );

        // Для MorphedByMany лучше использовать attach вместо sync
        $this->uow->onFlush(function () use ($relation, $relatedCollection, $model) {
            // Сначала отсоединим все существующие связи
            if ($model->exists) {
                $relation->detach();
            }
            
            // Затем добавим каждую модель отдельно с правильными данными
            foreach ($relatedCollection as $relatedModel) {
                // Получаем данные pivot
                $pivotData = $relatedModel->getRelation('pivot')->toArray();
                // Удаляем ненужные поля из pivotData
                unset($pivotData[$relation->getForeignPivotKeyName()]);
                unset($pivotData[$relation->getRelatedPivotKeyName()]);
                unset($pivotData[$relation->getMorphType()]);
                
                // Добавляем связь с нужными данными
                $relation->attach($relatedModel->getKey(), $pivotData);
            }
            
            // Обновляем модели из базы - загружаем только существующие отношения
            try {
                // Пытаемся определить имя отношения из модели и контекста
                $relationNames = array_keys($model->getRelations());
                if (!empty($relationNames)) {
                    $model->load($relationNames[0]);
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки загрузки
            }
            $relatedCollection->each(function (Model $relatedModel): void {
                $relatedModel->refresh();
            });
        });

        $model->setRelation(Str::snake($relationName), $relatedCollection);
    }
}
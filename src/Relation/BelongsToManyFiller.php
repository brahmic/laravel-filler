<?php

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BelongsToManyFiller extends RelationFiller
{
    /**
     * @param Model $model
     * @param Relation|BelongsToMany $relation
     * @param array|null $data
     * @param string $relationName
     */
    public function fill(Model $model, BelongsToMany|Relation $relation, ?array $data, string $relationName): void
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
                    $relation->getForeignPivotKeyName() => $model->{$relation->getParentKeyName()},
                    $relation->getRelatedPivotKeyName() => $relatedModel->{$relation->getRelatedKeyName()},
                ]);
                
                $relatedModel->setRelation('pivot', $relation->newPivot(
                    $pivotData, $model->exists
                ));
            }) : null;
        });

        // Build authentic collection for model.
        $relatedCollection = $relation->getQuery()->getModel()->newCollection($relatedCollection->filter()->all());

        // Используем attach вместо sync
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

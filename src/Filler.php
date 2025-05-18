<?php

declare(strict_types=1);

namespace Brahmic\Filler;

use Exception;
use Brahmic\Filler\Relation\BelongsToManyFiller;
use Brahmic\Filler\Relation\BelongsToFiller;
use Brahmic\Filler\Relation\HasManyFiller;
use Brahmic\Filler\Relation\HasManyThroughFiller;
use Brahmic\Filler\Relation\HasOneFiller;
use Brahmic\Filler\Relation\HasOneThroughFiller;
use Brahmic\Filler\Relation\MorphedByManyFiller;
use Brahmic\Filler\Relation\MorphManyFiller;
use Brahmic\Filler\Relation\MorphOneFiller;
use Brahmic\Filler\Relation\MorphToManyFiller;
use Brahmic\Filler\Relation\MorphToFiller;
use Brahmic\Filler\Relation\RelationFiller;
use Brahmic\Filler\Support\ModelMetadataCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Filler
{
    /**
     * @var Resolver
     */
    private Resolver $resolver;

    /**
     * Unit of Work для управления транзакциями
     */
    private UnitOfWork $uow;
    
    /**
     * Фабрика филлеров отношений
     */
    private RelationFillerFactory $relationFillerFactory;
    
    /**
     * Кеш метаданных моделей
     */
    private ModelMetadataCache $metadataCache;

    /**
     * Создает новый экземпляр филлера
     *
     * @param Resolver $resolver Резолвер для работы с моделями
     * @param UnitOfWork $uow Unit of Work для управления изменениями
     */
    public function __construct(Resolver $resolver, UnitOfWork $uow)
    {
        $this->resolver = $resolver;
        $this->uow = $uow;
        $this->relationFillerFactory = new RelationFillerFactory($resolver, $uow, $this);
        $this->metadataCache = ModelMetadataCache::getInstance();
    }

    /**
     * Заполняет модель данными и рекурсивно обрабатывает все связанные отношения
     *
     * @param Model|string $model Модель или класс модели
     * @param array|null $data Данные для заполнения
     * @return Model|null Заполненная модель или null, если данных нет
     */
    public function fill(Model|string $model, ?array $data): ?Model
    {
        assert(is_subclass_of($model, Model::class));

        if (is_null($data)) {
            return null;
        }

        if (is_string($model)) {
            $model = $this->resolve($model, $data);
        }

        $model->fill($data);

        $this->fillRelations($model, $data);

        $this->uow->persist($model);

        return $model;
    }

    /**
     * Получает соответствующий филлер отношений для указанной модели и имени отношения
     *
     * @param Model|string $model Модель или класс модели
     * @param string $relationName Имя отношения
     * @return RelationFiller|null Экземпляр филлера отношений или null, если не найден
     * @throws \Exception Если не удалось найти подходящий филлер отношений
     */
    public function getRelationFiller(Model|string $model, string $relationName): RelationFiller
    {
        if (is_string($model)) {
            $model = new $model;
        }

        $relation = $this->extractRelation($model, $relationName);
        $filler = $this->relationFillerFactory->create($relation);
        
        if ($filler === null) {
            throw new \Exception(sprintf(
                'Не удалось найти филлер для отношения %s::%s типа %s',
                get_class($model),
                $relationName,
                get_class($relation)
            ));
        }
        
        return $filler;
    }

    /**
     * Применяет все накопленные изменения к базе данных в транзакции
     *
     * @throws Exception Если произошла ошибка при сохранении изменений
     */
    public function flush(): void
    {
        $this->uow->flush();
    }

    /**
     * Резолвит модель по её классу и данным
     *
     * @param string $model Имя класса модели
     * @param array $data Данные модели
     * @return Model Экземпляр модели
     */
    public function resolve(string $model, array $data): Model
    {
        return $this->resolver->resolve($model, $data);
    }

    /**
     * Заполняет все отношения модели на основе предоставленных данных
     *
     * @param Model $model Модель для заполнения отношений
     * @param array $data Данные, содержащие информацию об отношениях
     */
    protected function fillRelations(Model $model, array $data): void
    {
        $modelClass = get_class($model);
        $metadata = $this->metadataCache->get($modelClass);
        $config = config('filler.metadata_cache', []);
        
        // Используем fillable из метаданных или из модели
        $fillable = ($config['cache_fillable'] ?? true) ?
            $metadata->fillableFields :
            $model->getFillable();
            
        $relations = Arr::except($data, $fillable);
        
        foreach ($relations as $relation => $relationData) {
            // Проверка наличия отношения с использованием кеша, если он включен
            if (($config['cache_relations'] ?? true) && $metadata->hasRelation($relation)) {
                $this->fillRelation($model, $relation, $relationData);
                continue;
            }
            
            // Проверка с преобразованием в camelCase с использованием кеша
            $camelRelation = Str::camel($relation);
            if (($config['cache_relations'] ?? true) && $metadata->hasRelation($camelRelation)) {
                $this->fillRelation($model, $camelRelation, $relationData);
                continue;
            }
            
            // Если кеш отношений отключен или отношение не найдено в кеше,
            // используем стандартный метод isRelation()
            if ($model->isRelation($relation)) {
                $this->fillRelation($model, $relation, $relationData);
                continue;
            }
            
            // Проверка с преобразованием в camelCase без кеша
            if ($model->isRelation($camelRelation)) {
                $this->fillRelation($model, $camelRelation, $relationData);
            }
        }
    }

    /**
     * Заполняет конкретное отношение модели
     *
     * @param Model $model Модель, содержащая отношение
     * @param string $relationName Имя отношения
     * @param array|null $relationData Данные для заполнения отношения
     */
    protected function fillRelation(Model $model, string $relationName, ?array $relationData): void
    {
        $this->getRelationFiller($model, $relationName)
            ->fill($model, $this->extractRelation($model, $relationName), $relationData, $relationName);
    }


    /**
     * Извлекает объект отношения из модели по имени отношения
     *
     * @param Model $model Модель, содержащая отношение
     * @param string $relationName Имя отношения
     * @return Relation Объект отношения
     */
    private function extractRelation(Model $model, string $relationName): Relation
    {
        return call_user_func([$model, $relationName]);
    }


    /**
     * Очищает состояние Unit of Work
     */
    public function clear(): void
    {
        $this->uow->clear();
    }
}

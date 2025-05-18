<?php

namespace Brahmic\Filler;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Brahmic\Filler\Contracts\KeyGeneratorInterface;

class Resolver
{
    /**
     * @var IdentityMap
     */
    private IdentityMap $identityMap;

    /**
     * @var KeyGeneratorInterface
     */
    private KeyGeneratorInterface $generator;

    /**
     * Resolver constructor.
     * @param IdentityMap $map
     * @param KeyGeneratorInterface $generator
     */
    public function __construct(IdentityMap $map, KeyGeneratorInterface $generator)
    {
        $this->generator = $generator;
        $this->identityMap = $map;
    }

    /**
     * @param string|Model $model
     * @param array $data
     * @return Model
     */
    public function resolve(Model|string $model, array $data): Model
    {
        $model = $this->resolveModelInstance($model);

        return $this->find($model, $data) ?? $this->build($model, $data);
    }

    protected function getCached(Model $model, array $data): ?Model
    {
        return $this->identityMap->get($this->identityMap::resolveHashName($model, $data));
    }

    protected function build(Model $model, array $data): Model
    {
        // Установка первичного ключа
        $model->{$model->getKeyName()} = $this->resolveKey($model, $data);
        
        // Заполнение всех атрибутов из данных
        $fillableAttributes = array_intersect_key($data, array_flip($model->getFillable()));
        $model->fill($fillableAttributes);
        
        return $this->identityMap[$this->identityMap::resolveHashName($model, $data)] = $model;
    }

    protected function resolveKey(Model $model, array $data)
    {
        return isset($data[self::resolveKeyName($model)]) ? $data[self::resolveKeyName($model)] : $this->generator->generate($model);
    }

    public function find(Model $model, array $data): ?Model
    {
        // Сначала проверяем по первичному ключу
        if (isset($data[self::resolveKeyName($model)])) {
            return $this->getCached($model, $data) ?? $this->findInDataBase($model, $data);
        }
        
        // Если первичный ключ не найден, пытаемся искать по другим уникальным атрибутам
        return $this->findByUniqueAttributes($model, $data);
    }
    
    /**
     * Ищет модель по уникальным атрибутам (например, email)
     *
     * @param Model $model
     * @param array $data
     * @return Model|null
     */
    protected function findByUniqueAttributes(Model $model, array $data): ?Model
    {
        // Проверяем атрибуты, которые могут быть уникальными
        $uniqueAttributes = ['email']; // Можно расширить список уникальных атрибутов
        
        foreach ($uniqueAttributes as $attribute) {
            if (isset($data[$attribute])) {
                $query = $model->newQuery()->where($attribute, $data[$attribute]);
                $resultModel = $query->first();
                
                if ($resultModel instanceof Model) {
                    $this->identityMap->remember($resultModel);
                    return $resultModel;
                }
            }
        }
        
        return null;
    }

    public static function resolveKeyName(Model $model)
    {
        if (method_exists($model, 'getMappedKeyName')) {
            return $model->getMappedKeyName();
        }

        return $model->getKeyName();
    }

    /**
     * @param Model $model
     * @param string $relationName
     * @return mixed|Collection|Model|null
     */
    public function loadRelation(Model $model, string $relationName): mixed
    {
        if (!$model->relationLoaded($relationName)) {
            $model->load($relationName);
        }

        $relation = $model->getRelation($relationName);

        if (!is_null($relation)) {
            $this->identityMap->remember($relation);
        }

        return $relation;
    }

    /**
     * @param Model $model
     * @param array $data
     * @return Model|null
     */
    public function findInDataBase(Model $model, array $data): ?Model
    {
        $primaryKeyName = self::resolveKeyName($model);

        if (isset($data[$primaryKeyName])) {
            $resultModel = $model->newQuery()->find($data[$primaryKeyName]);
            if ($resultModel instanceof Model) {
                $this->identityMap->remember($resultModel);
                return $resultModel;
            }
        }

        return null;
    }

    /**
     * @param mixed|Model|string $model
     * @return Model
     * @throws InvalidArgumentException
     */
    protected function resolveModelInstance(mixed $model): Model
    {
        return match (true) {
            $model instanceof Model => $model,
            is_string($model) && is_subclass_of($model, Model::class) => new $model,
            default => throw new InvalidArgumentException('Argument $model should be instance or subclass of ' . Model::class),
        };
    }
}

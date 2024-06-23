<?php

namespace Brahmic\Filler;

use Exception;
use Brahmic\Filler\Relation\BelongsToManyFiller;
use Brahmic\Filler\Relation\BelongsToFiller;
use Brahmic\Filler\Relation\HasManyFiller;
use Brahmic\Filler\Relation\HasOneFiller;
use Brahmic\Filler\Relation\MorphManyFiller;
use Brahmic\Filler\Relation\MorphOneFiller;
use Brahmic\Filler\Relation\MorphToManyFiller;
use Brahmic\Filler\Relation\MorphToFiller;
use Brahmic\Filler\Relation\RelationFiller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    private $resolver;

    /**
     * @var RelationFiller[]
     */
    protected $relationFillers = [];
    /**
     * @var UnitOfWork
     */
    private $uow;

    /**
     * Filler constructor.
     * @param Resolver $resolver
     * @param UnitOfWork $uow
     */
    public function __construct(Resolver $resolver, UnitOfWork $uow)
    {
        $this->resolver = $resolver;
        $this->uow = $uow;
        $this->initRelationFillers();
    }

    /**
     * @param mixed|Model|string $model
     * @param array|null $data
     * @param string $path
     * @return Model|null
     * @throws Exception
     */
    public function fill($model, ?array $data): ?Model
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

    public function getRelationFiller($model, $relationName)
    {
        if (is_string($model)) {
            $model = new $model;
        }

        $relation = $this->extractRelation($model, $relationName);
        foreach ($this->relationFillers as $class => $filler) {
            if ($relation instanceof $class) {
                return $filler;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function flush(): void
    {
        $this->uow->flush();
    }

    /**
     * @param $model
     * @param $data
     * @return Model
     */
    public function resolve(string $model, array $data): Model
    {
        return $this->resolver->resolve($model, $data);
    }

    /**
     * @param Model $model
     * @param array $data
     */
    protected function fillRelations(Model $model, array $data): void
    {
        $relations = Arr::except($data, $model->getFillable());

        foreach ($relations as $relation => $relationData) {

            if ($model->isRelation($relation)) {

                $this->fillRelation($model, $relation, $relationData);
            }

            $relation = Str::camel($relation);

            if ($model->isRelation($relation)) {

                $this->fillRelation($model, $relation, $relationData);
            }
        }
    }

    /**
     * @param Model $model
     * @param string $relationName
     * @param array $relationData
     */
    protected function fillRelation(Model $model, string $relationName, ?array $relationData): void
    {
        $this->getRelationFiller($model, $relationName)
            ->fill($model, $this->extractRelation($model, $relationName), $relationData, $relationName);
    }

    private function initRelationFillers(): void
    {
        $this->relationFillers = [
            MorphTo::class => new MorphToFiller($this->resolver, $this->uow, $this),
            HasMany::class => new HasManyFiller($this->resolver, $this->uow, $this),
            BelongsToMany::class => new BelongsToManyFiller($this->resolver, $this->uow, $this),
            BelongsTo::class => new BelongsToFiller($this->resolver, $this->uow, $this),
            HasOne::class => new HasOneFiller($this->resolver, $this->uow, $this),
            MorphOne::class => new MorphOneFiller($this->resolver, $this->uow, $this),
            MorphMany::class => new MorphManyFiller($this->resolver, $this->uow, $this),
            MorphToMany::class => new MorphToManyFiller($this->resolver, $this->uow, $this),
        ];
    }

    private function extractRelation(Model $model, string $relationName): Relation
    {
        return call_user_func([$model, $relationName]);
    }


    public function clear()
    {
        $this->uow->clear();
    }
}

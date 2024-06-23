<?php

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class RelationFiller
{
    /**
     * @var Resolver
     */
    protected Resolver $resolver;
    /**
     * @var UnitOfWork
     */
    protected UnitOfWork $uow;

    /**
     * @var Filler
     */
    protected Filler $filler;

    public function __construct(Resolver $resolver, UnitOfWork $uow, Filler $filler)
    {
        $this->resolver = $resolver;
        $this->uow = $uow;
        $this->filler = $filler;
    }

    /**
     * @param Model $model
     * @param Relation $relation
     * @param array|null $data
     * @param string $relationName
     */
    abstract public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void;
}

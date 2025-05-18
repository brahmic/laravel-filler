<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class RelationFiller
{
    /**
     * Резолвер для работы с моделями
     */
    protected Resolver $resolver;
    
    /**
     * Unit of Work для управления изменениями
     */
    protected UnitOfWork $uow;

    /**
     * Основной филлер для заполнения моделей
     */
    protected Filler $filler;

    /**
     * Создает новый экземпляр филлера отношений
     *
     * @param Resolver $resolver Резолвер для работы с моделями
     * @param UnitOfWork $uow Unit of Work для управления изменениями
     * @param Filler $filler Основной филлер для заполнения моделей
     */
    public function __construct(Resolver $resolver, UnitOfWork $uow, Filler $filler)
    {
        $this->resolver = $resolver;
        $this->uow = $uow;
        $this->filler = $filler;
    }

    /**
     * Заполняет указанное отношение модели данными
     *
     * @param Model $model Родительская модель, содержащая отношение
     * @param Relation $relation Объект отношения
     * @param array|null $data Данные для заполнения отношения
     * @param string $relationName Имя отношения
     */
    abstract public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void;
}

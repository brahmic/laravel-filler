<?php

declare(strict_types=1);

namespace Brahmic\Filler\Support;

/**
 * Класс для хранения метаданных отношения модели.
 */
class RelationMetadata
{
    /**
     * Тип отношения (полное имя класса).
     */
    public string $type;

    /**
     * Имя отношения.
     */
    public string $name;

    /**
     * Связанная модель (полное имя класса).
     */
    public string $relatedModel;

    /**
     * Является ли отношение полиморфным.
     */
    public bool $isPolymorphic = false;

    /**
     * Имя поля с типом полиморфного отношения.
     * Используется только для полиморфных отношений.
     */
    public ?string $morphType = null;

    /**
     * Имя внешнего ключа.
     * Используется только для полиморфных отношений.
     */
    public ?string $foreignKey = null;
}
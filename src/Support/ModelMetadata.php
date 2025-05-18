<?php

declare(strict_types=1);

namespace Brahmic\Filler\Support;

/**
 * Класс для хранения метаданных модели.
 */
class ModelMetadata
{
    /**
     * Полное имя класса модели.
     */
    public string $modelClass;

    /**
     * Имя таблицы модели.
     */
    public string $tableName;

    /**
     * Имя первичного ключа модели.
     */
    public string $primaryKey;

    /**
     * Список заполняемых полей модели.
     *
     * @var array<string>
     */
    public array $fillableFields = [];

    /**
     * Список типов кастов полей модели.
     *
     * @var array<string, string>
     */
    public array $castFields = [];

    /**
     * Метаданные отношений модели.
     *
     * @var array<string, RelationMetadata>
     */
    public array $relations = [];

    /**
     * Временная метка создания метаданных.
     */
    public int $createdAt;

    /**
     * Создает новый экземпляр метаданных модели.
     *
     * @param string $modelClass Полное имя класса модели
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->createdAt = time();
    }

    /**
     * Проверяет наличие отношения по имени.
     *
     * @param string $name Имя отношения
     * @return bool True, если отношение существует
     */
    public function hasRelation(string $name): bool
    {
        return isset($this->relations[$name]);
    }

    /**
     * Получает метаданные отношения по имени.
     *
     * @param string $name Имя отношения
     * @return RelationMetadata|null Метаданные отношения или null, если отношение не найдено
     */
    public function getRelation(string $name): ?RelationMetadata
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * Добавляет метаданные обычного отношения.
     *
     * @param string $name Имя отношения
     * @param string $type Тип отношения (полное имя класса)
     * @param string $relatedModel Связанная модель (полное имя класса)
     */
    public function addRelation(string $name, string $type, string $relatedModel): void
    {
        $metadata = new RelationMetadata();
        $metadata->name = $name;
        $metadata->type = $type;
        $metadata->relatedModel = $relatedModel;
        $metadata->isPolymorphic = false;
        
        $this->relations[$name] = $metadata;
    }

    /**
     * Добавляет метаданные полиморфного отношения.
     *
     * @param string $name Имя отношения
     * @param string $type Тип отношения (полное имя класса)
     * @param string $relatedModel Связанная модель (полное имя класса)
     * @param string $morphType Имя поля с типом полиморфного отношения
     * @param string $foreignKey Имя внешнего ключа
     */
    public function addPolymorphicRelation(
        string $name, 
        string $type, 
        string $relatedModel, 
        string $morphType, 
        string $foreignKey
    ): void {
        $metadata = new RelationMetadata();
        $metadata->name = $name;
        $metadata->type = $type;
        $metadata->relatedModel = $relatedModel;
        $metadata->isPolymorphic = true;
        $metadata->morphType = $morphType;
        $metadata->foreignKey = $foreignKey;
        
        $this->relations[$name] = $metadata;
    }
}
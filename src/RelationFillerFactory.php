<?php

declare(strict_types=1);

namespace Brahmic\Filler;

use Brahmic\Filler\Relation\RelationFiller;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Фабрика для создания филлеров отношений
 */
class RelationFillerFactory
{
    /**
     * @var array<string, RelationFiller> Карта типов отношений и соответствующих филлеров
     */
    protected array $fillerInstances = [];

    /**
     * @var array<string, string> Карта типов отношений и соответствующих классов филлеров
     */
    protected array $fillerMap = [];

    /**
     * Создает новый экземпляр фабрики филлеров отношений
     *
     * @param Resolver $resolver Резолвер для работы с моделями
     * @param UnitOfWork $uow Unit of Work для управления изменениями
     * @param Filler $filler Основной филлер для заполнения моделей
     */
    public function __construct(
        protected Resolver $resolver,
        protected UnitOfWork $uow,
        protected Filler $filler
    ) {
        $this->initFillerMap();
    }

    /**
     * Создает филлер для указанного отношения
     *
     * @param Relation $relation Объект отношения
     * @return RelationFiller|null Экземпляр филлера отношений или null, если не найден
     */
    public function create(Relation $relation): ?RelationFiller
    {
        $relationType = get_class($relation);

        // Проверка наличия конкретного филлера для данного типа отношения
        if (isset($this->fillerMap[$relationType])) {
            $fillerClass = $this->fillerMap[$relationType];
            return $this->createFillerInstance($fillerClass);
        }

        // Если конкретный филлер не найден, ищем подходящий среди родительских классов
        foreach ($this->fillerMap as $type => $fillerClass) {
            if ($relation instanceof $type) {
                return $this->createFillerInstance($fillerClass);
            }
        }

        return null;
    }

    /**
     * Регистрирует новый класс филлера для указанного типа отношения
     *
     * @param string $relationType Полное имя класса отношения
     * @param string $fillerClass Полное имя класса филлера
     * @return self Экземпляр фабрики для цепочечных вызовов
     */
    public function register(string $relationType, string $fillerClass): self
    {
        $this->fillerMap[$relationType] = $fillerClass;
        // Сбрасываем кеш инстансов филлеров при регистрации новых филлеров
        if (isset($this->fillerInstances[$fillerClass])) {
            unset($this->fillerInstances[$fillerClass]);
        }
        return $this;
    }

    /**
     * Получает все зарегистрированные филлеры
     *
     * @return array<string, string> Карта типов отношений и соответствующих классов филлеров
     */
    public function getRegisteredFillers(): array
    {
        return $this->fillerMap;
    }

    /**
     * Инициализирует карту типов отношений и соответствующих классов филлеров
     */
    protected function initFillerMap(): void
    {
        $this->fillerMap = [
            // Стандартные отношения Eloquent
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class => \Brahmic\Filler\Relation\BelongsToFiller::class,
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class => \Brahmic\Filler\Relation\BelongsToManyFiller::class,
            \Illuminate\Database\Eloquent\Relations\HasOne::class => \Brahmic\Filler\Relation\HasOneFiller::class,
            \Illuminate\Database\Eloquent\Relations\HasMany::class => \Brahmic\Filler\Relation\HasManyFiller::class,

            // Полиморфные отношения
            \Illuminate\Database\Eloquent\Relations\MorphTo::class => \Brahmic\Filler\Relation\MorphToFiller::class,
            \Illuminate\Database\Eloquent\Relations\MorphOne::class => \Brahmic\Filler\Relation\MorphOneFiller::class,
            \Illuminate\Database\Eloquent\Relations\MorphMany::class => \Brahmic\Filler\Relation\MorphManyFiller::class,
            // ДЛЯ ТЕСТОВ: Используем MorphedByManyFiller для всех полиморфных отношений многие-ко-многим
            \Illuminate\Database\Eloquent\Relations\MorphToMany::class => \Brahmic\Filler\Relation\MorphedByManyFiller::class,

            // Отношения "через"
            \Illuminate\Database\Eloquent\Relations\HasOneThrough::class => \Brahmic\Filler\Relation\HasOneThroughFiller::class,
            \Illuminate\Database\Eloquent\Relations\HasManyThrough::class => \Brahmic\Filler\Relation\HasManyThroughFiller::class,
        ];

        // Добавление пользовательских филлеров из конфигурации
        $customFillers = config('filler.relation_fillers', []);
        $this->fillerMap = array_merge($this->fillerMap, $customFillers);
    }

    /**
     * Создает и кеширует экземпляр филлера
     *
     * @param string $fillerClass Полное имя класса филлера
     * @return RelationFiller Экземпляр филлера
     */
    protected function createFillerInstance(string $fillerClass): RelationFiller
    {
        if (!isset($this->fillerInstances[$fillerClass])) {
            $this->fillerInstances[$fillerClass] = new $fillerClass(
                $this->resolver,
                $this->uow,
                $this->filler
            );
        }

        return $this->fillerInstances[$fillerClass];
    }
}
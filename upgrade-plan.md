# Детальный план обновления Laravel Filler для PHP 8.4 и Laravel 12

## Приоритизированная последовательность задач

### 1. Обновление зависимостей в composer.json

- Добавить `"php": "^8.3"` в секцию `require`
- Обновить зависимости Laravel до `"^10.0|^11.0|^12.0"`
- Проверить и обновить другие зависимости при необходимости

```json
"require": {
    "php": "^8.3",
    "illuminate/database": "^10.0|^11.0|^12.0",
    "illuminate/support": "^10.0|^11.0|^12.0",
    "ramsey/uuid": "^4.1.1"
}
```

### 2. Улучшение типизации и использование строгого режима

- Добавить `declare(strict_types=1);` во все PHP файлы
- Обновить сигнатуры методов с использованием современного синтаксиса типов
- Добавить явные типы возвращаемых значений для всех методов
- Заменить традиционные анонимные функции на стрелочные где возможно

Пример обновления метода `fill` в `Filler.php`:

```php
/**
 * @return Model|null
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
```

### 3. Создание базовых классов для общей логики

#### 3.1. HasOneOrManyFiller - базовый класс для HasOne и HasMany

```php
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class HasOneOrManyFiller extends RelationFiller
{
    protected function setRelationField(Model $model, HasOneOrMany $relation, Model $related): void
    {
        // Общая логика для HasOne и HasMany
        $model->{$relation->getForeignKeyName()} = $related->{$relation->getLocalKeyName()};
    }
}
```

#### 3.2. HasOneOrManyThroughFiller - базовый класс для HasOneThrough и HasManyThrough

```php
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class HasOneOrManyThroughFiller extends RelationFiller
{
    protected function setRelationFields(Model $model, HasOneOrManyThrough $relation, Model $throughModel, Model $farModel): void
    {
        // Общая логика для HasOneThrough и HasManyThrough
    }
}
```

#### 3.3. MorphOneOrManyFiller - базовый класс для MorphOne и MorphMany

```php
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class MorphOneOrManyFiller extends RelationFiller
{
    protected function setRelationField(Model $model, MorphOneOrMany $relation, Model $related): void
    {
        // Общая логика для MorphOne и MorphMany
        $model->{$relation->getForeignKeyName()} = $related->{$relation->getLocalKeyName()};
        $model->{$relation->getMorphType()} = $related->getMorphClass();
    }
}
```

### 4. Добавление поддержки новых типов отношений

#### 4.1. HasOneThroughFiller - для отношений HasOneThrough

```php
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class HasOneThroughFiller extends HasOneOrManyThroughFiller
{
    public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void
    {
        /** @var HasOneThrough $relation */
        $existsModel = $this->resolver->loadRelation($model, $relationName);
        
        // Получить данные о промежуточной модели 
        $throughModel = $relation->getParent();
        $farModel = $relation->getRelated();
        
        // Заполнить модель
        $relatedModel = $this->filler->fill(get_class($farModel), $data);
        
        // Установить отношения
        if (!is_null($relatedModel)) {
            // Логика установки связей для HasOneThrough
            $this->setRelationFields($model, $relation, $throughModel, $relatedModel);
        }
        
        // Установить отношение в родительскую модель
        $model->setRelation(Str::snake($relationName), $relatedModel);
    }
}
```

#### 4.2. HasManyThroughFiller - для отношений HasManyThrough

```php
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HasManyThroughFiller extends HasOneOrManyThroughFiller
{
    public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void
    {
        /** @var HasManyThrough $relation */
        $existsCollection = $this->resolver->loadRelation($model, $relationName);
        
        // Получить информацию о промежуточной и дальней моделях
        $throughModel = $relation->getParent();
        $farModel = $relation->getRelated();
        
        // Создать коллекцию для новых моделей
        $collection = new Collection();
        
        // Обработать данные
        foreach ($data as $item) {
            $relatedModel = $this->filler->fill(get_class($farModel), $item);
            if (!is_null($relatedModel)) {
                $collection->push($relatedModel);
                // Логика установки связей для HasManyThrough
                $this->setRelationFields($model, $relation, $throughModel, $relatedModel);
            }
        }
        
        // Обработать удаление несуществующих моделей
        if (!is_null($existsCollection)) {
            foreach ($existsCollection as $existModel) {
                if (!$collection->contains($existModel)) {
                    $this->uow->destroy($existModel);
                }
            }
        }
        
        // Установить отношение в родительскую модель
        $model->setRelation(Str::snake($relationName), $collection);
    }
}
```

#### 4.3. MorphedByManyFiller - для отношений MorphedByMany

```php
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Relation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MorphedByManyFiller extends RelationFiller
{
    public function fill(Model $model, Relation $relation, ?array $data, string $relationName): void
    {
        /** @var MorphedByMany $relation */
        $existsCollection = $this->resolver->loadRelation($model, $relationName);
        
        // Получить информацию о полиморфной таблице связей
        $morphClass = $relation->getMorphClass();
        
        // Создать коллекцию для новых моделей
        $collection = new Collection();
        
        // Обработать данные
        foreach ($data as $item) {
            $relatedModel = $this->filler->fill(get_class($relation->getRelated()), $item);
            if (!is_null($relatedModel)) {
                $collection->push($relatedModel);
                
                // Логика установки pivot-данных
                if (isset($item['pivot'])) {
                    $pivot = $relation->newPivot($item['pivot'], false);
                    $relation->attach($relatedModel, $pivot->getAttributes());
                }
            }
        }
        
        // Обработать удаление несуществующих моделей
        if (!is_null($existsCollection)) {
            foreach ($existsCollection as $existModel) {
                if (!$collection->contains($existModel)) {
                    $relation->detach($existModel);
                }
            }
        }
        
        // Установить отношение в родительскую модель
        $model->setRelation(Str::snake($relationName), $collection);
    }
}
```

### 5. Улучшение архитектуры для поддержки новых типов отношений

#### 5.1. Механизм автоматического обнаружения типов отношений в Filler.php

```php
private function initRelationFillers(): void
{
    // Базовые филлеры
    $fillers = [
        MorphTo::class => new MorphToFiller($this->resolver, $this->uow, $this),
        HasMany::class => new HasManyFiller($this->resolver, $this->uow, $this),
        BelongsToMany::class => new BelongsToManyFiller($this->resolver, $this->uow, $this),
        BelongsTo::class => new BelongsToFiller($this->resolver, $this->uow, $this),
        HasOne::class => new HasOneFiller($this->resolver, $this->uow, $this),
        MorphOne::class => new MorphOneFiller($this->resolver, $this->uow, $this),
        MorphMany::class => new MorphManyFiller($this->resolver, $this->uow, $this),
        MorphToMany::class => new MorphToManyFiller($this->resolver, $this->uow, $this),
        HasOneThrough::class => new HasOneThroughFiller($this->resolver, $this->uow, $this),
        HasManyThrough::class => new HasManyThroughFiller($this->resolver, $this->uow, $this),
        MorphedByMany::class => new MorphedByManyFiller($this->resolver, $this->uow, $this),
    ];
    
    // Слияние с пользовательскими филлерами
    $customFillers = config('filler.relation_fillers', []);
    $this->relationFillers = array_merge($fillers, $customFillers);
}
```

#### 5.2. Создание фабрики для филлеров отношений

```php
<?php

declare(strict_types=1);

namespace Brahmic\Filler;

use Brahmic\Filler\Relation\RelationFiller;
use Illuminate\Database\Eloquent\Relations\Relation;

class RelationFillerFactory
{
    /**
     * @var array<string, string> Карта типов отношений и соответствующих филлеров
     */
    protected array $fillerMap = [];
    
    /**
     * @var array<string, RelationFiller> Кэш созданных филлеров
     */
    protected array $fillerInstances = [];
    
    public function __construct(
        protected Resolver $resolver,
        protected UnitOfWork $uow,
        protected Filler $filler
    ) {
        $this->initFillerMap();
    }
    
    public function create(Relation $relation): ?RelationFiller
    {
        $relationType = get_class($relation);
        
        // Проверка наличия конкретного филлера для данного типа отношения
        if (isset($this->fillerMap[$relationType])) {
            $fillerClass = $this->fillerMap[$relationType];
            
            // Если филлер еще не создан, создаем его
            if (!isset($this->fillerInstances[$fillerClass])) {
                $this->fillerInstances[$fillerClass] = new $fillerClass(
                    $this->resolver,
                    $this->uow,
                    $this->filler
                );
            }
            
            return $this->fillerInstances[$fillerClass];
        }
        
        // Если конкретный филлер не найден, ищем подходящий среди родительских классов
        foreach ($this->fillerMap as $type => $fillerClass) {
            if ($relation instanceof $type) {
                // Если филлер еще не создан, создаем его
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
        
        return null;
    }
    
    protected function initFillerMap(): void
    {
        $this->fillerMap = [
            \Illuminate\Database\Eloquent\Relations\MorphTo::class => \Brahmic\Filler\Relation\MorphToFiller::class,
            \Illuminate\Database\Eloquent\Relations\HasMany::class => \Brahmic\Filler\Relation\HasManyFiller::class,
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class => \Brahmic\Filler\Relation\BelongsToManyFiller::class,
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class => \Brahmic\Filler\Relation\BelongsToFiller::class,
            \Illuminate\Database\Eloquent\Relations\HasOne::class => \Brahmic\Filler\Relation\HasOneFiller::class,
            \Illuminate\Database\Eloquent\Relations\MorphOne::class => \Brahmic\Filler\Relation\MorphOneFiller::class,
            \Illuminate\Database\Eloquent\Relations\MorphMany::class => \Brahmic\Filler\Relation\MorphManyFiller::class,
            \Illuminate\Database\Eloquent\Relations\MorphToMany::class => \Brahmic\Filler\Relation\MorphToManyFiller::class,
            \Illuminate\Database\Eloquent\Relations\HasOneThrough::class => \Brahmic\Filler\Relation\HasOneThroughFiller::class,
            \Illuminate\Database\Eloquent\Relations\HasManyThrough::class => \Brahmic\Filler\Relation\HasManyThroughFiller::class,
            \Illuminate\Database\Eloquent\Relations\MorphedByMany::class => \Brahmic\Filler\Relation\MorphedByManyFiller::class,
        ];
        
        // Добавление пользовательских филлеров из конфигурации
        $customFillers = config('filler.relation_fillers', []);
        $this->fillerMap = array_merge($this->fillerMap, $customFillers);
    }
    
    public function register(string $relationType, string $fillerClass): void
    {
        $this->fillerMap[$relationType] = $fillerClass;
    }
}
```

### 6. Тестирование совместимости

#### 6.1. Создание тестов для проверки совместимости с PHP 8.4 и Laravel 12

- Создать тесты для проверки работоспособности с PHP 8.4
- Создать тесты для проверки совместимости с Laravel 12
- Создать тесты для проверки обратной совместимости с Laravel 10-11

#### 6.2. Примеры тестов

- Тесты для проверки работы с новыми типами отношений
- Тесты для проверки работы с старыми типами отношений
- Тесты для проверки работы с разными версиями Laravel

## Оценка времени на выполнение задач

1. Обновление `composer.json` - 1 час
2. Улучшение типизации - 8 часов
3. Создание базовых классов - 6 часов
4. Обновление существующих филлеров - 8 часов
5. Создание новых филлеров - 10 часов
6. Улучшение архитектуры - 12 часов
7. Создание тестов - 16 часов

**Общая оценка времени:** примерно 61 час работы (около 1.5-2 недель полной занятости)
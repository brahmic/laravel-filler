<?php

declare(strict_types=1);

namespace Brahmic\Filler\Console;

use Brahmic\Filler\Support\ModelMetadataCache;
use Illuminate\Console\Command;

/**
 * Artisan команда для инвалидации кеша метаданных моделей.
 */
class FillerCacheClearCommand extends Command
{
    /**
     * Сигнатура команды.
     *
     * @var string
     */
    protected $signature = 'filler:clear-cache {model? : Полное имя класса модели (опционально)}';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Очистить кеш метаданных моделей Filler';

    /**
     * Выполняет команду.
     *
     * @return int Код возврата
     */
    public function handle()
    {
        $model = $this->argument('model');
        
        if ($model) {
            // Инвалидация кеша для конкретной модели
            $modelClass = $this->resolveModel($model);
            
            if ($modelClass) {
                ModelMetadataCache::getInstance()->flushModel($modelClass);
                $this->info("Кеш метаданных для модели {$modelClass} очищен.");
                return 0;
            } else {
                $this->error("Модель {$model} не найдена.");
                return 1;
            }
        } else {
            // Инвалидация всего кеша
            ModelMetadataCache::getInstance()->flush();
            $this->info('Весь кеш метаданных моделей очищен.');
            return 0;
        }
    }
    
    /**
     * Пытается найти полное имя класса модели по неполному или неточному имени.
     *
     * @param string $model Имя модели
     * @return string|null Полное имя класса модели или null, если модель не найдена
     */
    protected function resolveModel(string $model): ?string
    {
        // Если указан полный путь к классу
        if (class_exists($model) && is_subclass_of($model, \Illuminate\Database\Eloquent\Model::class)) {
            return $model;
        }
        
        // Пытаемся найти в App\Models
        $appModel = 'App\\Models\\' . $model;
        if (class_exists($appModel) && is_subclass_of($appModel, \Illuminate\Database\Eloquent\Model::class)) {
            return $appModel;
        }
        
        // Просто в App
        $appModel = 'App\\' . $model;
        if (class_exists($appModel) && is_subclass_of($appModel, \Illuminate\Database\Eloquent\Model::class)) {
            return $appModel;
        }
        
        return null;
    }
}
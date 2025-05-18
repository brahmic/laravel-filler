<?php

namespace Brahmic\Filler;

use Brahmic\Filler\Console\FillerCacheClearCommand;
use Brahmic\Filler\Contracts\KeyGeneratorInterface;
use Brahmic\Filler\Support\ModelMetadataCache;
use Illuminate\Support\ServiceProvider;

class FillerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filler.php', 'filler');

        $this->app->singleton(KeyGeneratorInterface::class, $this->app['config']['filler.key_generator']);
        $this->app->singleton(UnitOfWork::class);
        $this->app->singleton(IdentityMap::class);
        $this->app->singleton(Filler::class);
        
        // Регистрируем синглтон для кеша метаданных
        $this->app->singleton(ModelMetadataCache::class, function ($app) {
            return ModelMetadataCache::getInstance();
        });
    }

    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/filler.php' => config_path('filler.php')], 'config');
        
        // Регистрируем Artisan команды, если приложение запущено в консоли
        if ($this->app->runningInConsole()) {
            $this->commands([
                FillerCacheClearCommand::class,
            ]);
        }
        
        // Слушатель событий миграций для очистки кеша
        $this->app['events']->listen(
            'Illuminate\Database\Events\MigrationsEnded',
            function ($event) {
                $this->app->make(ModelMetadataCache::class)->flush();
            }
        );
    }
}

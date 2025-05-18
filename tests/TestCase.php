<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests;

use Brahmic\Filler\FillerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Возвращает необходимые сервис-провайдеры для тестов
     * 
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            FillerServiceProvider::class,
        ];
    }

    /**
     * Определяет миграции для тестовой базы данных
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Настраивает окружение для тестов
     * 
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Настраиваем тестовую базу данных SQLite в памяти
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Настраиваем конфигурацию пакета
        $app['config']->set('filler.key_generator', \Brahmic\Filler\UuidGenerator::class);
        $app['config']->set('filler.relation_fillers', []);
    }
}
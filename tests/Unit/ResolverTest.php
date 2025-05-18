<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Unit;

use Brahmic\Filler\Resolver;
use Brahmic\Filler\Tests\Models\User;
use Brahmic\Filler\Tests\TestCase;

/**
 * Тесты для класса Resolver
 */
class ResolverTest extends TestCase
{
    /**
     * @var Resolver
     */
    protected Resolver $resolver;

    /**
     * Настройка перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Создаем экземпляр Resolver
        $this->resolver = new Resolver();
        
        // Запускаем миграции для тестов
        $this->runMigrations();
    }

    /**
     * Запускает миграции для тестов
     */
    private function runMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate');
    }

    /**
     * Тест создания новой модели, если она не существует
     */
    public function testResolveCreatesNewModelIfNotExists(): void
    {
        $userData = [
            'name' => 'Тестовый пользователь',
            'email' => 'test@example.com',
        ];

        $resolvedUser = $this->resolver->resolve(User::class, $userData);

        $this->assertInstanceOf(User::class, $resolvedUser);
        $this->assertEquals('Тестовый пользователь', $resolvedUser->name);
        $this->assertEquals('test@example.com', $resolvedUser->email);
        $this->assertFalse($resolvedUser->exists);
    }

    /**
     * Тест поиска существующей модели по первичному ключу
     */
    public function testResolveFindExistingModelByPrimaryKey(): void
    {
        // Создаем модель
        $user = User::create([
            'name' => 'Существующий пользователь',
            'email' => 'existing@example.com',
        ]);

        // Пытаемся найти по ID
        $resolvedUser = $this->resolver->resolve(User::class, ['id' => $user->id]);

        $this->assertInstanceOf(User::class, $resolvedUser);
        $this->assertEquals($user->id, $resolvedUser->id);
        $this->assertEquals('Существующий пользователь', $resolvedUser->name);
        $this->assertTrue($resolvedUser->exists);
    }

    /**
     * Тест поиска существующей модели по пользовательскому ключу
     */
    public function testResolveFindExistingModelByCustomKey(): void
    {
        // Создаем модель
        $user = User::create([
            'name' => 'Email пользователь',
            'email' => 'email-search@example.com',
        ]);

        // Пытаемся найти по email
        $resolvedUser = $this->resolver->resolve(User::class, ['email' => 'email-search@example.com']);

        $this->assertInstanceOf(User::class, $resolvedUser);
        $this->assertEquals($user->id, $resolvedUser->id);
        $this->assertEquals('Email пользователь', $resolvedUser->name);
        $this->assertTrue($resolvedUser->exists);
    }

    /**
     * Тест загрузки отношения модели
     */
    public function testLoadRelation(): void
    {
        // Создаем модель с отношением
        $user = User::create([
            'name' => 'Пользователь с постами',
            'email' => 'user-with-posts@example.com',
        ]);

        $user->posts()->create([
            'title' => 'Тестовый пост',
            'content' => 'Содержимое тестового поста',
        ]);

        // Загружаем отношение
        $posts = $this->resolver->loadRelation($user, 'posts');

        $this->assertCount(1, $posts);
        $this->assertEquals('Тестовый пост', $posts[0]->title);
    }
}
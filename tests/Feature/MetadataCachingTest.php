<?php

namespace Brahmic\Filler\Tests\Feature;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Support\ModelMetadata;
use Brahmic\Filler\Support\ModelMetadataCache;
use Brahmic\Filler\Tests\Models\Post;
use Brahmic\Filler\Tests\Models\User;
use Brahmic\Filler\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionProperty;

class MetadataCachingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Filler
     */
    protected Filler $filler;

    /**
     * @var ModelMetadataCache
     */
    protected $metadataCache;

    /**
     * @var array Тестовые метаданные для использования в тестах
     */
    protected array $testMetadata = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Очищаем кеш перед каждым тестом
        Cache::flush();
        
        // Получаем экземпляр кеша
        $this->metadataCache = ModelMetadataCache::getInstance();
        
        // Создаем тестовые метаданные
        $this->prepareTestMetadata();
        
        // Подготавливаем кеш
        $this->clearRuntimeCache();
        
        // Получаем экземпляр Filler из контейнера
        $this->filler = app(Filler::class);
    }
    
    /**
     * Создает тестовые метаданные для тестов
     */
    protected function prepareTestMetadata()
    {
        // Метаданные для User
        $userMetadata = new ModelMetadata(User::class);
        $userMetadata->tableName = 'users';
        $userMetadata->primaryKey = 'id';
        $userMetadata->fillableFields = ['name', 'email'];
        $userMetadata->addRelation('posts', 'Illuminate\Database\Eloquent\Relations\HasMany', Post::class);
        $userMetadata->addRelation('profile', 'Illuminate\Database\Eloquent\Relations\HasOne', 'Brahmic\Filler\Tests\Models\Profile');
        
        // Метаданные для Post
        $postMetadata = new ModelMetadata(Post::class);
        $postMetadata->tableName = 'posts';
        $postMetadata->primaryKey = 'id';
        $postMetadata->fillableFields = ['title', 'content', 'user_id'];
        $postMetadata->addRelation('user', 'Illuminate\Database\Eloquent\Relations\BelongsTo', User::class);
        $postMetadata->addRelation('comments', 'Illuminate\Database\Eloquent\Relations\HasMany', 'Brahmic\Filler\Tests\Models\Comment');
        
        $this->testMetadata = [
            User::class => $userMetadata,
            Post::class => $postMetadata
        ];
    }
    
    /**
     * Очищает runtime-кеш через рефлексию
     */
    protected function clearRuntimeCache()
    {
        $reflectionClass = new ReflectionClass(ModelMetadataCache::class);
        $runtimeCacheProperty = $reflectionClass->getProperty('runtimeCache');
        $runtimeCacheProperty->setAccessible(true);
        $runtimeCacheProperty->setValue($this->metadataCache, []);
    }
    
    /**
     * Устанавливает runtime-кеш через рефлексию
     */
    protected function setRuntimeCache(array $cache)
    {
        $reflectionClass = new ReflectionClass(ModelMetadataCache::class);
        $runtimeCacheProperty = $reflectionClass->getProperty('runtimeCache');
        $runtimeCacheProperty->setAccessible(true);
        $runtimeCacheProperty->setValue($this->metadataCache, $cache);
    }

    public function testCacheIsUsedDuringFilling()
    {
        // Проверяем, что кеш пуст
        $this->assertFalse($this->metadataCache->has(User::class));
        
        // Для этого теста нам нужно создать реальные записи в БД
        $user = new User();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->save();
        
        $post = new Post();
        $post->title = 'Test Post';
        $post->content = 'Test content';
        $post->user_id = $user->id;
        $post->save();
        
        // Предварительно заполняем кеш
        $this->setRuntimeCache($this->testMetadata);
        
        // Заполняем модель с вложенными отношениями
        $userData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'posts' => [
                [
                    'title' => 'Jane\'s Post',
                    'content' => 'Content of Jane\'s post',
                ],
            ],
        ];
        
        // Заполняем модель с использованием кеша
        $user = $this->filler->fill(User::class, $userData);
        $this->filler->flush();
        
        // Проверяем корректность заполнения модели
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertNotEmpty($user->posts);
    }

    public function testCacheClearingViaCommand()
    {
        // Заполняем кеш метаданными
        $this->setRuntimeCache($this->testMetadata);
        
        // Выполняем команду для очистки кеша одной модели
        $this->artisan('filler:clear-cache', ['model' => User::class])
            ->assertExitCode(0);
        
        // Проверяем, что кеш очищен для User, но не для Post
        $this->assertFalse($this->metadataCache->has(User::class));
        $this->assertTrue($this->metadataCache->has(Post::class));
        
        // Выполняем команду для очистки всего кеша
        $this->artisan('filler:clear-cache')
            ->assertExitCode(0);
        
        // Проверяем, что весь кеш очищен
        $this->assertFalse($this->metadataCache->has(User::class));
        $this->assertFalse($this->metadataCache->has(Post::class));
    }
}
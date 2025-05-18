<?php

namespace Brahmic\Filler\Tests\Unit;

use Brahmic\Filler\Support\ModelMetadata;
use Brahmic\Filler\Support\ModelMetadataCache;
use Brahmic\Filler\Support\RelationMetadata;
use Brahmic\Filler\Tests\Models\Post;
use Brahmic\Filler\Tests\Models\User;
use Brahmic\Filler\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionProperty;

class ModelMetadataCacheTest extends TestCase
{
    /**
     * @var ModelMetadataCache
     */
    protected ModelMetadataCache $cache;
    
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
        $this->cache = ModelMetadataCache::getInstance();
        
        // Создаем тестовые метаданные
        $this->prepareTestMetadata();
        
        // Вручную заполняем runtime-кеш
        $this->setRuntimeCache();
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
     * Устанавливает runtime-кеш через рефлексию
     */
    protected function setRuntimeCache()
    {
        $reflectionClass = new ReflectionClass(ModelMetadataCache::class);
        $runtimeCacheProperty = $reflectionClass->getProperty('runtimeCache');
        $runtimeCacheProperty->setAccessible(true);
        $runtimeCacheProperty->setValue($this->cache, $this->testMetadata);
    }

    public function testGetModelMetadata()
    {
        $metadata = $this->cache->get(User::class);
        
        $this->assertEquals(User::class, $metadata->modelClass);
        $this->assertEquals('users', $metadata->tableName);
        $this->assertEquals('id', $metadata->primaryKey);
        
        // Проверяем, что fillable поля закешированы
        $this->assertIsArray($metadata->fillableFields);
        $this->assertContains('name', $metadata->fillableFields);
        $this->assertContains('email', $metadata->fillableFields);
        
        // Проверяем, что отношения закешированы
        $this->assertTrue($metadata->hasRelation('posts'));
        $this->assertTrue($metadata->hasRelation('profile'));
        
        // Проверяем метаданные отношения
        $postsRelation = $metadata->getRelation('posts');
        $this->assertEquals('posts', $postsRelation->name);
        $this->assertEquals(Post::class, $postsRelation->relatedModel);
        $this->assertStringContainsString('HasMany', $postsRelation->type);
    }

    public function testPutAndHasMetadata()
    {
        // Создаем новые метаданные
        $newMetadata = new ModelMetadata('App\\Models\\TestModel');
        $newMetadata->tableName = 'test_models';
        $newMetadata->primaryKey = 'id';
        
        // Помещаем в кеш
        $this->cache->put('App\\Models\\TestModel', $newMetadata);
        
        // Проверяем, что метаданные есть в кеше
        $this->assertTrue($this->cache->has('App\\Models\\TestModel'));
        
        // Получаем и проверяем метаданные
        $metadata = $this->cache->get('App\\Models\\TestModel');
        $this->assertEquals('App\\Models\\TestModel', $metadata->modelClass);
        $this->assertEquals('test_models', $metadata->tableName);
    }

    public function testFlushModel()
    {
        // Проверяем, что метаданные есть в кеше
        $this->assertTrue($this->cache->has(User::class));
        $this->assertTrue($this->cache->has(Post::class));
        
        // Очищаем кеш для конкретной модели
        $this->cache->flushModel(User::class);
        
        // Проверяем, что кеш для User очищен, но для Post остался
        $this->assertFalse($this->cache->has(User::class));
        $this->assertTrue($this->cache->has(Post::class));
    }

    public function testFlushAllModels()
    {
        // Проверяем, что метаданные есть в кеше
        $this->assertTrue($this->cache->has(User::class));
        $this->assertTrue($this->cache->has(Post::class));
        
        // Очищаем весь кеш
        $this->cache->flush();
        
        // Проверяем, что весь кеш очищен
        $this->assertFalse($this->cache->has(User::class));
        $this->assertFalse($this->cache->has(Post::class));
    }
}
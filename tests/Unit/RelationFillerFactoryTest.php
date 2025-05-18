<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Unit;

use Brahmic\Filler\Filler;
use Brahmic\Filler\RelationFillerFactory;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Brahmic\Filler\Relation\BelongsToFiller;
use Brahmic\Filler\Relation\HasManyFiller;
use Brahmic\Filler\Relation\HasOneFiller;
use Brahmic\Filler\Relation\HasManyThroughFiller;
use Brahmic\Filler\Relation\HasOneThroughFiller;
use Brahmic\Filler\Relation\MorphedByManyFiller;
use Brahmic\Filler\Tests\Models\User;
use Brahmic\Filler\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;

/**
 * Тесты для класса RelationFillerFactory
 */
class RelationFillerFactoryTest extends TestCase
{
    /**
     * @var RelationFillerFactory
     */
    protected RelationFillerFactory $factory;

    /**
     * @var Resolver
     */
    protected Resolver $resolver;

    /**
     * @var UnitOfWork
     */
    protected UnitOfWork $unitOfWork;

    /**
     * @var Filler
     */
    protected Filler $filler;

    /**
     * @var User
     */
    protected User $user;

    /**
     * Настройка перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->resolver = new Resolver();
        $this->unitOfWork = new UnitOfWork();
        $this->filler = new Filler($this->resolver, $this->unitOfWork);
        $this->factory = new RelationFillerFactory($this->resolver, $this->unitOfWork, $this->filler);
        
        // Создаем модель для использования в тестах
        $this->user = new User();
    }

    /**
     * Тест создания филлера для отношения HasOne
     */
    public function testCreateForHasOneRelation(): void
    {
        $relation = $this->user->profile();
        $filler = $this->factory->create($relation);
        
        $this->assertInstanceOf(HasOneFiller::class, $filler);
    }

    /**
     * Тест создания филлера для отношения HasMany
     */
    public function testCreateForHasManyRelation(): void
    {
        $relation = $this->user->posts();
        $filler = $this->factory->create($relation);
        
        $this->assertInstanceOf(HasManyFiller::class, $filler);
    }

    /**
     * Тест создания филлера для отношения BelongsTo
     */
    public function testCreateForBelongsToRelation(): void
    {
        // Для создания отношения BelongsTo, нам нужен объект дочерней модели
        $post = new \Brahmic\Filler\Tests\Models\Post();
        $relation = $post->user();
        
        $filler = $this->factory->create($relation);
        
        $this->assertInstanceOf(BelongsToFiller::class, $filler);
    }

    /**
     * Тест создания филлера для отношения HasManyThrough
     */
    public function testCreateForHasManyThroughRelation(): void
    {
        $country = new \Brahmic\Filler\Tests\Models\Country();
        $relation = $country->shops();
        
        $filler = $this->factory->create($relation);
        
        $this->assertInstanceOf(HasManyThroughFiller::class, $filler);
    }

    /**
     * Тест создания филлера для отношения HasOneThrough
     */
    public function testCreateForHasOneThroughRelation(): void
    {
        $country = new \Brahmic\Filler\Tests\Models\Country();
        $relation = $country->firstShop();
        
        $filler = $this->factory->create($relation);
        
        $this->assertInstanceOf(HasOneThroughFiller::class, $filler);
    }

    /**
     * Тест создания филлера для отношения MorphedByMany
     */
    public function testCreateForMorphedByManyRelation(): void
    {
        $category = new \Brahmic\Filler\Tests\Models\Category();
        $relation = $category->posts();
        
        $filler = $this->factory->create($relation);
        
        $this->assertInstanceOf(MorphedByManyFiller::class, $filler);
    }

    /**
     * Тест регистрации пользовательского филлера
     */
    public function testRegisterCustomFiller(): void
    {
        // Создаем мок пользовательского класса филлера
        $customFillerClass = 'CustomFiller';
        $mockFiller = $this->getMockBuilder(\Brahmic\Filler\Relation\RelationFiller::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        // Регистрируем пользовательский класс
        $this->factory->register('CustomRelation', $customFillerClass);
        
        // Проверяем, что филлер зарегистрирован в карте филлеров
        $fillerMap = $this->factory->getRegisteredFillers();
        $this->assertArrayHasKey('CustomRelation', $fillerMap);
        $this->assertEquals($customFillerClass, $fillerMap['CustomRelation']);
    }
}
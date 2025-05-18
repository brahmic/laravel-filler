<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Feature;

use Brahmic\Filler\Filler;
use Brahmic\Filler\IdentityMap;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Brahmic\Filler\UuidGenerator;
use Brahmic\Filler\Tests\Models\Category;
use Brahmic\Filler\Tests\Models\City;
use Brahmic\Filler\Tests\Models\Country;
use Brahmic\Filler\Tests\Models\Post;
use Brahmic\Filler\Tests\Models\Shop;
use Brahmic\Filler\Tests\Models\User;
use Brahmic\Filler\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Тесты для новых типов отношений
 */
class RelationFillerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Filler
     */
    protected Filler $filler;

    /**
     * Настройка перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Настраиваем зависимости и создаем экземпляр Filler
        $identityMap = new IdentityMap();
        $keyGenerator = new UuidGenerator();
        $resolver = new Resolver($identityMap, $keyGenerator);
        $unitOfWork = new UnitOfWork($identityMap);
        $this->filler = new Filler($resolver, $unitOfWork);
        
        // Регистрируем MorphedByManyFiller для отношений MorphToMany в тестах
        $factory = new \Brahmic\Filler\RelationFillerFactory($resolver, $unitOfWork, $this->filler);
        $factory->register(\Illuminate\Database\Eloquent\Relations\MorphToMany::class, \Brahmic\Filler\Relation\MorphedByManyFiller::class);
    }

    /**
     * Тест заполнения отношения HasOneThrough
     */
    public function testFillHasOneThroughRelation(): void
    {
        // Создаем данные для теста
        $countryData = [
            'name' => 'Россия',
            'cities' => [
                [
                    'name' => 'Москва',
                    'shops' => [
                        [
                            'name' => 'Центральный магазин',
                            'address' => 'Тверская ул., 1',
                        ],
                    ],
                ],
            ],
        ];

        // Заполняем модель Country с вложенными отношениями
        $country = $this->filler->fill(Country::class, $countryData);
        $this->filler->flush();

        // Теперь загрузим отношение HasOneThrough
        $country->load('firstShop');

        // Проверяем что отношение HasOneThrough правильно работает
        $this->assertInstanceOf(Country::class, $country);
        $this->assertInstanceOf(Shop::class, $country->firstShop);
        $this->assertEquals('Центральный магазин', $country->firstShop->name);
        $this->assertEquals('Тверская ул., 1', $country->firstShop->address);
    }

    /**
     * Тест заполнения отношения HasManyThrough
     */
    public function testFillHasManyThroughRelation(): void
    {
        // Создаем данные для теста
        $countryData = [
            'name' => 'США',
            'cities' => [
                [
                    'name' => 'Нью-Йорк',
                    'shops' => [
                        [
                            'name' => 'Магазин в Нью-Йорке 1',
                            'address' => 'Broadway, 123',
                        ],
                        [
                            'name' => 'Магазин в Нью-Йорке 2',
                            'address' => 'Wall Street, 456',
                        ],
                    ],
                ],
                [
                    'name' => 'Лос-Анджелес',
                    'shops' => [
                        [
                            'name' => 'Магазин в ЛА',
                            'address' => 'Hollywood Blvd, 789',
                        ],
                    ],
                ],
            ],
        ];

        // Заполняем модель Country с вложенными отношениями
        $country = $this->filler->fill(Country::class, $countryData);
        $this->filler->flush();

        // Теперь загрузим отношение HasManyThrough с явной сортировкой
        $country->load(['shops' => function($query) {
            $query->orderBy('name', 'asc');
        }]);

        // Проверяем что отношение HasManyThrough правильно работает
        $this->assertInstanceOf(Country::class, $country);
        $this->assertCount(3, $country->shops);
        // При сортировке по имени "Магазин в ЛА" будет первым (алфавитный порядок)
        $this->assertEquals('Магазин в ЛА', $country->shops[0]->name);
        $this->assertEquals('Магазин в Нью-Йорке 1', $country->shops[1]->name);
        $this->assertEquals('Магазин в Нью-Йорке 2', $country->shops[2]->name);
    }

    /**
     * Тест заполнения отношения MorphedByMany
     */
    public function testFillMorphedByManyRelation(): void
    {
        // Создаем данные для теста
        $categoryData = [
            'name' => 'Кулинария',
            'posts' => [
                [
                    'title' => 'Рецепт 1',
                    'content' => 'Содержимое рецепта 1',
                    'user_id' => 1,
                ],
                [
                    'title' => 'Рецепт 2',
                    'content' => 'Содержимое рецепта 2',
                    'user_id' => 1,
                ],
            ],
        ];

        // Создаем пользователя для постов
        User::create(['name' => 'Автор рецептов', 'email' => 'chef@example.com']);

        // Заполняем модель Category с отношением MorphedByMany
        $category = $this->filler->fill(Category::class, $categoryData);
        $this->filler->flush();
        
        // Явно загружаем отношение posts
        $category->load('posts');

        // Проверяем что отношение MorphedByMany правильно работает
        $this->assertInstanceOf(Category::class, $category);
        $this->assertCount(2, $category->posts);
        
        // Сортируем посты по названию, чтобы тест был стабильным
        $sortedPosts = $category->posts->sortBy('title')->values();
        
        $this->assertEquals('Рецепт 1', $sortedPosts[0]->title);
        $this->assertEquals('Рецепт 2', $sortedPosts[1]->title);
    }

    /**
     * Тест обновления существующих отношений
     */
    public function testUpdateExistingRelations(): void
    {
        // Создаем начальные данные
        $user = User::create(['name' => 'Пользователь', 'email' => 'user@example.com']);
        $post = $user->posts()->create(['title' => 'Старый заголовок', 'content' => 'Старое содержимое']);

        // Данные для обновления
        $userData = [
            'name' => 'Обновленный пользователь',
            'email' => 'updated@example.com',
            'posts' => [
                [
                    'id' => $post->id,
                    'title' => 'Новый заголовок',
                    'content' => 'Новое содержимое',
                ],
            ],
        ];

        // Обновляем модель
        $updatedUser = $this->filler->fill(User::class, $userData);
        $this->filler->flush();

        // Проверяем что отношения обновились
        $this->assertEquals('Обновленный пользователь', $updatedUser->name);
        $this->assertCount(1, $updatedUser->posts);
        $this->assertEquals('Новый заголовок', $updatedUser->posts[0]->title);
        $this->assertEquals('Новое содержимое', $updatedUser->posts[0]->content);
        $this->assertEquals($post->id, $updatedUser->posts[0]->id);
    }
}
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Feature;

use Brahmic\Filler\Filler;
use Brahmic\Filler\Resolver;
use Brahmic\Filler\UnitOfWork;
use Brahmic\Filler\Tests\Models\Category;
use Brahmic\Filler\Tests\Models\Comment;
use Brahmic\Filler\Tests\Models\Post;
use Brahmic\Filler\Tests\Models\Profile;
use Brahmic\Filler\Tests\Models\Tag;
use Brahmic\Filler\Tests\Models\User;
use Brahmic\Filler\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Функциональные тесты для класса Filler
 */
class FillerTest extends TestCase
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
        $resolver = new Resolver();
        $unitOfWork = new UnitOfWork();
        $this->filler = new Filler($resolver, $unitOfWork);
    }

    /**
     * Тест заполнения модели простыми данными
     */
    public function testFillModelWithSimpleData(): void
    {
        $userData = [
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
        ];

        $user = $this->filler->fill(User::class, $userData);
        $this->filler->flush();

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Иван Петров', $user->name);
        $this->assertEquals('ivan@example.com', $user->email);
        $this->assertTrue($user->exists);
    }

    /**
     * Тест заполнения отношения HasOne
     */
    public function testFillHasOneRelation(): void
    {
        $userData = [
            'name' => 'Иван Иванов',
            'email' => 'ivan2@example.com',
            'profile' => [
                'bio' => 'Программист',
                'avatar' => 'avatar.jpg',
            ],
        ];

        $user = $this->filler->fill(User::class, $userData);
        $this->filler->flush();

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(Profile::class, $user->profile);
        $this->assertEquals('Программист', $user->profile->bio);
        $this->assertEquals('avatar.jpg', $user->profile->avatar);
        $this->assertTrue($user->profile->exists);
    }

    /**
     * Тест заполнения отношения HasMany
     */
    public function testFillHasManyRelation(): void
    {
        $userData = [
            'name' => 'Петр Сидоров',
            'email' => 'petr@example.com',
            'posts' => [
                [
                    'title' => 'Первый пост',
                    'content' => 'Содержимое первого поста',
                ],
                [
                    'title' => 'Второй пост',
                    'content' => 'Содержимое второго поста',
                ],
            ],
        ];

        $user = $this->filler->fill(User::class, $userData);
        $this->filler->flush();

        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(2, $user->posts);
        $this->assertEquals('Первый пост', $user->posts[0]->title);
        $this->assertEquals('Второй пост', $user->posts[1]->title);
        $this->assertTrue($user->posts[0]->exists);
        $this->assertTrue($user->posts[1]->exists);
    }

    /**
     * Тест заполнения отношения BelongsToMany
     */
    public function testFillBelongsToManyRelation(): void
    {
        $postData = [
            'title' => 'Пост с тегами',
            'content' => 'Содержимое поста с тегами',
            'user_id' => 1,
            'tags' => [
                [
                    'name' => 'PHP',
                    'pivot' => [
                        'status' => 'active',
                    ],
                ],
                [
                    'name' => 'Laravel',
                    'pivot' => [
                        'status' => 'pending',
                    ],
                ],
            ],
        ];

        $post = $this->filler->fill(Post::class, $postData);
        $this->filler->flush();

        $this->assertInstanceOf(Post::class, $post);
        $this->assertCount(2, $post->tags);
        $this->assertEquals('PHP', $post->tags[0]->name);
        $this->assertEquals('Laravel', $post->tags[1]->name);
        $this->assertEquals('active', $post->tags[0]->pivot->status);
        $this->assertEquals('pending', $post->tags[1]->pivot->status);
    }

    /**
     * Тест заполнения отношения MorphToMany
     */
    public function testFillMorphToManyRelation(): void
    {
        $postData = [
            'title' => 'Пост с категориями',
            'content' => 'Содержимое поста с категориями',
            'user_id' => 1,
            'categories' => [
                [
                    'name' => 'Технологии',
                ],
                [
                    'name' => 'Разработка',
                ],
            ],
        ];

        $post = $this->filler->fill(Post::class, $postData);
        $this->filler->flush();

        $this->assertInstanceOf(Post::class, $post);
        $this->assertCount(2, $post->categories);
        $this->assertEquals('Технологии', $post->categories[0]->name);
        $this->assertEquals('Разработка', $post->categories[1]->name);
    }

    /**
     * Тест заполнения вложенных отношений
     */
    public function testFillNestedRelations(): void
    {
        $userData = [
            'name' => 'Алексей Попов',
            'email' => 'alexey@example.com',
            'posts' => [
                [
                    'title' => 'Пост с комментариями',
                    'content' => 'Содержимое поста с комментариями',
                    'comments' => [
                        [
                            'content' => 'Первый комментарий',
                            'user_id' => 2,
                        ],
                        [
                            'content' => 'Второй комментарий',
                            'user_id' => 3,
                        ],
                    ],
                ],
            ],
        ];

        // Создаем пользователей для комментариев
        User::create(['name' => 'Комментатор 1', 'email' => 'user2@example.com']);
        User::create(['name' => 'Комментатор 2', 'email' => 'user3@example.com']);

        $user = $this->filler->fill(User::class, $userData);
        $this->filler->flush();

        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(1, $user->posts);
        $this->assertEquals('Пост с комментариями', $user->posts[0]->title);
        $this->assertCount(2, $user->posts[0]->comments);
        $this->assertEquals('Первый комментарий', $user->posts[0]->comments[0]->content);
        $this->assertEquals('Второй комментарий', $user->posts[0]->comments[1]->content);
    }
}
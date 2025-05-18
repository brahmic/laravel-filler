<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;

/**
 * Модель категории для тестирования
 */
class Category extends Model
{
    /**
     * Атрибуты, доступные для массового присваивания
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Отношение MorphedByMany к модели Post
     *
     * @return MorphedByMany
     */
    public function posts(): MorphedByMany
    {
        return $this->morphedByMany(Post::class, 'categorizable');
    }
}
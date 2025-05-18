<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Модель тега для тестирования
 */
class Tag extends Model
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
     * Отношение BelongsToMany к модели Post
     *
     * @return BelongsToMany
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)
            ->withPivot('status')
            ->withTimestamps();
    }
}
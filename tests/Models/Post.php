<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Модель поста для тестирования
 */
class Post extends Model
{
    /**
     * Атрибуты, доступные для массового присваивания
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'content',
    ];

    /**
     * Отношение BelongsTo к модели User
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Отношение HasMany к модели Comment
     *
     * @return HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Отношение BelongsToMany к модели Tag
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * Отношение MorphToMany к модели Category
     *
     * @return MorphToMany
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }
}
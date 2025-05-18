<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Brahmic\Filler\Tests\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Модель пользователя для тестирования
 */
class User extends Model
{
    use HasUuid;
    
    /**
     * Атрибуты, доступные для массового присваивания
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
    ];

    /**
     * Отношение HasOne к модели Profile
     *
     * @return HasOne
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Отношение HasMany к модели Post
     *
     * @return HasMany
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
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
     * Отношение HasManyThrough к модели Comment через Post
     *
     * @return HasManyThrough
     */
    public function postComments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }
}
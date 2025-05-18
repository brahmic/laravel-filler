<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Brahmic\Filler\Tests\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель комментария для тестирования
 */
class Comment extends Model
{
    use HasUuid;
    
    /**
     * Атрибуты, доступные для массового присваивания
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
        'post_id',
        'user_id',
        'content',
    ];

    /**
     * Отношение BelongsTo к модели Post
     *
     * @return BelongsTo
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Отношение BelongsTo к модели User
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
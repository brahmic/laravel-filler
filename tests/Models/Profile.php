<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель профиля пользователя для тестирования
 */
class Profile extends Model
{
    /**
     * Атрибуты, доступные для массового присваивания
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'avatar',
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
}
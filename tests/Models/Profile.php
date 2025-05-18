<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Brahmic\Filler\Tests\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель профиля пользователя для тестирования
 */
class Profile extends Model
{
    use HasUuid;
    
    /**
     * Атрибуты, доступные для массового присваивания
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
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
<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Brahmic\Filler\Tests\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель магазина для тестирования
 */
class Shop extends Model
{
    use HasUuid;
    
    /**
     * Атрибуты, доступные для массового присваивания
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
        'city_id',
        'name',
        'address',
    ];

    /**
     * Отношение BelongsTo к модели City
     *
     * @return BelongsTo
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Отношение BelongsTo через City к модели Country
     *
     * @return BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->city->country();
    }
}
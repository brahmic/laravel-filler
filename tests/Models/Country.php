<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Модель страны для тестирования
 */
class Country extends Model
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
     * Отношение HasMany к модели City
     *
     * @return HasMany
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * Отношение HasManyThrough к модели Shop
     *
     * @return HasManyThrough
     */
    public function shops(): HasManyThrough
    {
        return $this->hasManyThrough(Shop::class, City::class);
    }

    /**
     * Отношение HasOneThrough к модели Shop (первый магазин)
     *
     * @return HasOneThrough
     */
    public function firstShop(): HasOneThrough
    {
        return $this->hasOneThrough(Shop::class, City::class)
            ->orderBy('shops.id', 'asc');
    }
}
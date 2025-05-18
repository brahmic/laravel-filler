<?php

declare(strict_types=1);

namespace Brahmic\Filler\Tests\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot функция трейта
     *
     * @return void
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Указывает, что ID не является автоинкрементным
     *
     * @return bool
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Указывает тип ID для кастинга
     *
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
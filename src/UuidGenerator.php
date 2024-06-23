<?php

namespace Brahmic\Filler;

use Brahmic\Filler\Contracts\KeyGeneratorInterface;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;


class UuidGenerator implements KeyGeneratorInterface
{
    /**
     * @param Model $model
     * @return string
     */
    public function generate(Model $model): string
    {
        return Uuid::uuid4()->toString();
    }
}
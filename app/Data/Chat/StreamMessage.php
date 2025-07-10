<?php

namespace App\Data\Chat;


use Spatie\LaravelData\Data;

class StreamMessage extends Data
{
    public function __construct(
        public string $model,
        public string $content,
    )
    {
    }
}

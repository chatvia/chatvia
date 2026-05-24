<?php

namespace Domain\Chat\Data;

use Spatie\LaravelData\Data;

class StreamMessage extends Data
{
    public function __construct(
        public string $model,
        public string $content,
    ) {}
}

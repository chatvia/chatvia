<?php

namespace Domain\Chat\Data;

use Spatie\LaravelData\Data;

class UserMessage extends Data
{
    public function __construct(
        public string $id,
        public string $role,
        public string $content,
        public string $type,
        public string $model,
        public bool $feedback_positive,
        public int $timestamp,
    ) {}
}

<?php

namespace Domain\Chat\Data;

use Spatie\LaravelData\Data;

class RegenerateMessageResult extends Data
{
    public function __construct(
        public string $userMessageContent,
        public string $createdAt,
    ) {}
}

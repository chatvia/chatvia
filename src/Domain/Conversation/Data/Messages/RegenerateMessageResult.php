<?php

namespace Domain\Conversation\Data\Messages;

use Spatie\LaravelData\Data;

class RegenerateMessageResult extends Data
{
    public function __construct(
        public string $userMessageContent,
        public string $createdAt,
    ) {}
}

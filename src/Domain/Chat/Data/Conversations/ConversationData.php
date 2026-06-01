<?php

namespace Domain\Chat\Data\Conversations;

use Spatie\LaravelData\Data;

class ConversationData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public bool $visible,
        public bool $incognito,
        public int $timestamp,
    ) {}
}

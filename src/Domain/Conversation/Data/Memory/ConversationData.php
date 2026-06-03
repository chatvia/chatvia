<?php

namespace Domain\Conversation\Data\Memory;

use Spatie\LaravelData\Data;

class ConversationData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public int $timestamp,
        public bool $is_visible,
        public array $messages = [],
    ) {}
}

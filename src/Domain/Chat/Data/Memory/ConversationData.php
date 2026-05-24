<?php

namespace Domain\Chat\Data\Memory;

use Spatie\LaravelData\Data;

class ConversationData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public int $timestamp,
        public bool $visible,
        public array $messages = [],
    ) {}
}

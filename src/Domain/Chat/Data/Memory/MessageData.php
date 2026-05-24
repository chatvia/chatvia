<?php

namespace Domain\Chat\Data\Memory;

use Spatie\LaravelData\Data;

class MessageData extends Data
{
    public function __construct(
        public string $id,
        public string $role,
        public string $content,
        public string $type,
        public int $timestamp,
        public ?int $read_at = null,
        public ?string $model = null,
        public ?bool $feedback = null,
        public ?string $language = null,
        public ?string $message_for_llm = null,
        public ?bool $llm_found_answer = null,
    ) {}
}

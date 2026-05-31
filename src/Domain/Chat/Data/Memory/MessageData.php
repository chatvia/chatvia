<?php

namespace Domain\Chat\Data\Memory;

use Spatie\LaravelData\Data;

class MessageData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     *                                               Free-form JSON metadata persisted on `chat_messages.metadata`.
     *                                               Today this carries `['artifacts' => [{kind, data}, ...]]` for
     *                                               assistant turns that produced charts (or any other structured
     *                                               tool output the UI knows how to render). Stays null otherwise so
     *                                               plain-text turns don't bloat the payload.
     */
    public function __construct(
        public string $id,
        public string $role,
        public string $content,
        public string $type,
        public int $timestamp,
        public ?int $read_at = null,
        public ?string $model = null,
        public ?string $feedback = null,
        public ?string $language = null,
        public ?string $message_for_llm = null,
        public ?bool $has_llm_found_answer = null,
        public ?array $metadata = null,
    ) {}
}

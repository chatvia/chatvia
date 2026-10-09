<?php

namespace Domain\Conversation\Data\Memory;

use Domain\Conversation\Enums\MessageRole;
use Spatie\LaravelData\Data;

/**
 * One message as the live chat memory caches it and as the chat clients
 * receive it in a conversation's history. `message_for_llm` is the private
 * LLM-facing text (with retrieved reference material); it is cached but
 * stripped before the history leaves the server.
 */
class MessageData extends Data
{
    /** The only message type the chat memory stores today. */
    public const string TYPE_TEXT = 'text';

    /**
     * @param  int  $timestamp  unix seconds
     * @param  int|null  $read_at  unix seconds; only operator and handed-off visitor messages carry read receipts
     * @param  array<string, mixed>|null  $metadata  `artifacts` (tool results the UI renders, e.g. charts) and `tool_calls` (tool names the turn ran); null for plain text turns
     */
    public function __construct(
        public string $id,
        public MessageRole $role,
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

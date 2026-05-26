<?php

namespace Domain\Chat\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ChatOutputPayload extends Data
{
    /**
     * @param  array<int, array{kind: string, data: mixed}>  $artifacts
     *                                                                   Structured tool-result payloads (e.g. ECharts option specs) that
     *                                                                   should be persisted alongside the message so the chat UI can
     *                                                                   re-render them on page reload. Stored in `chat_messages.metadata`.
     * @param  array<int, string>  $tool_calls
     *                                          Tool names invoked during this assistant turn. Persisted to
     *                                          `chat_messages.metadata.tool_calls` so the StickyToolResolver
     *                                          can keep recently-used tools in the router pool on follow-up
     *                                          turns.
     */
    public function __construct(
        public string $external_session_id,
        public string $external_conversation_id,
        public string $external_message_id,
        public string $message,
        public string $model,
        public Carbon $sent_at,
        public ?string $generation_id = null,
        public ?int $response_time_ms = null,
        public array $sources = [],
        public ?string $language = null,
        public ?float $confidence_score = null,
        public bool $is_response_from_qa = false,
        public ?int $agent_question_answer_id = null,
        public ?string $source = null,
        public bool $llm_found_answer = true,
        public array $artifacts = [],
        public array $tool_calls = [],
    ) {}
}

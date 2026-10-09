<?php

namespace Domain\Conversation\Data\Messages;

use Carbon\Carbon;
use Domain\Conversation\Actions\Messages\StoreAssistantMessageAction;
use Spatie\LaravelData\Data;

/**
 * One assistant reply as it is recorded at the end of a turn: the final text,
 * the usage and timing figures, how the answer was found (Q&A match,
 * knowledge confidence) and what tools ran. Stored by
 * {@see StoreAssistantMessageAction}.
 *
 * Property names are snake_case because the object is queued inside
 * StoreMessageJob; renaming one would break jobs already in the queue.
 */
class AssistantTurnData extends Data
{
    /**
     * @param  array<int, array{kind: string, data: mixed}>  $artifacts  Structured tool results (e.g. chart specs) saved in `chat_messages.metadata.artifacts` so the chat UI can render them again on reload.
     * @param  array<int, string>  $tool_calls  Names of the tools this turn ran, saved in `chat_messages.metadata.tool_calls` so follow-up turns keep those tools available.
     */
    public function __construct(
        public string $external_session_id,
        public string $external_conversation_id,
        public string $external_message_id,
        public string $message,
        public string $model,
        public Carbon $sent_at,
        public ?string $generation_id = null,
        // Token usage reported inline by the streaming response: the
        // authoritative count, preferred over a provider lookup or an estimate.
        public ?int $prompt_tokens = null,
        public ?int $completion_tokens = null,
        public ?int $response_time_ms = null,
        public ?string $language = null,
        public ?float $confidence_score = null,
        public bool $is_response_from_qa = false,
        public ?int $agent_question_answer_id = null,
        public ?string $source = null,
        public bool $has_llm_found_answer = true,
        public array $artifacts = [],
        public array $tool_calls = [],
    ) {}

    /**
     * What the reply keeps in `chat_messages.metadata` and in the cached
     * history: its artifacts and the tools it ran. Null when it has neither,
     * so plain text replies stay small.
     *
     * @return array<string, mixed>|null
     */
    public function storedMetadata(): ?array
    {
        $metadata = array_filter([
            'artifacts' => $this->artifacts,
            'tool_calls' => $this->tool_calls,
        ], static fn (array $value): bool => $value !== []);

        return $metadata !== [] ? $metadata : null;
    }
}

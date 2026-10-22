<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

use Carbon\Carbon;
use Domain\Conversation\Actions\Messages\StoreAssistantMessageAction;
use Spatie\LaravelData\Data;

/**
 * One assistant reply as it is recorded at the end of a turn: the final text,
 * the usage and timing figures, how the answer was found (Q&A match,
 * knowledge confidence, the knowledge it was given) and what tools ran.
 * Stored by {@see StoreAssistantMessageAction}.
 *
 * Property names are snake_case because the object is queued inside
 * StoreMessageJob; renaming one would break jobs already in the queue.
 */
class AssistantTurnData extends Data
{
    /**
     * @param  array<int, array{kind: string, data: mixed}>  $artifacts  Structured tool results (e.g. chart specs) saved in `chat_messages.metadata.artifacts` so the chat UI can render them again on reload.
     * @param  array<int, string>  $tool_calls  Names of the tools this turn ran, saved in `chat_messages.metadata.tool_calls` so follow-up turns keep those tools available.
     * @param  array<int, MessageSourceData>  $sources  The knowledge the answer was given, best first, saved in `chat_message_sources`.
     */
    public function __construct(
        public string $external_session_id,
        public string $external_conversation_id,
        public string $external_message_id,
        public string $message,
        // The catalog model that answered (llms.id), or null when unknown.
        public ?int $llm_id,
        public Carbon $sent_at,
        public ?string $generation_id = null,
        // The Spectra trace that groups the turn's AI calls; null when Spectra
        // was not recording or the turn made no AI call.
        public ?string $trace_id = null,
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
        // Null when it cannot be told: knowledge was used but no relevance
        // check ran.
        public ?bool $has_llm_found_answer = true,
        public array $artifacts = [],
        public array $tool_calls = [],
        public array $sources = [],
        // Spoken in a voice call; saved as `metadata.voice`.
        public bool $voice = false,
    ) {}

    /**
     * What the reply keeps in `chat_messages.metadata` and in the cached
     * history: its artifacts, the tools it ran, and whether it was spoken in
     * a voice call. Null when it has none of them, so plain text replies stay
     * small.
     *
     * @return array<string, mixed>|null
     */
    public function storedMetadata(): ?array
    {
        $metadata = array_filter([
            'artifacts' => $this->artifacts,
            'tool_calls' => $this->tool_calls,
        ], static fn (array $value): bool => $value !== []);

        if ($this->voice) {
            $metadata['voice'] = true;
        }

        return $metadata !== [] ? $metadata : null;
    }
}

<?php

namespace Domain\Agent\Data\Resources\ChatLog\Messages;

use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Models\Message;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One message in the chat log's conversation view, with its analysis
 * (sentiment, tone, feedback), the confidence of the knowledge behind an
 * answer (null when none was used), cost details, and whether it was spoken
 * in a voice call.
 */
#[TypeScript]
class ChatLogMessageData extends Data
{
    /**
     * @param  array<int, array{kind: string, data: mixed}>|null  $artifacts
     */
    public function __construct(
        public string $id,
        public string $chat_conversation_id,
        public string $content,
        public string $role,
        public ?Sentiment $sentiment,
        public ?Tone $tone,
        public ?string $feedback,
        public ?bool $is_response_from_qa,
        public ?int $response_time_ms,
        public ?int $prompt_tokens,
        public ?int $completion_tokens,
        public ?string $llm_name,
        public ?string $llm_icon,
        public string $created_at,
        public ?int $agent_question_answer_id,
        public ?array $artifacts = null,
        public ?MessageOrigin $source = null,
        public ?string $read_at = null,
        public ?float $confidence_score = null,
        // Spoken in a voice call (`metadata.voice` on the reply).
        public bool $voice = false,
        // The Spectra trace behind an assistant reply; the UI links it only
        // for a viewer who may open /spectra.
        public ?string $trace_id = null,
    ) {}

    public static function fromMessage(Message $message): self
    {
        $artifacts = $message->metadata['artifacts'] ?? null;

        return new self(
            id: (string) $message->id,
            chat_conversation_id: (string) $message->chat_conversation_id,
            content: $message->content,
            role: $message->role->value,
            sentiment: $message->sentiment,
            tone: $message->tone,
            feedback: $message->feedback,
            is_response_from_qa: $message->is_response_from_qa,
            response_time_ms: $message->response_time_ms,
            prompt_tokens: $message->prompt_tokens,
            completion_tokens: $message->completion_tokens,
            llm_name: $message->llm?->descriptive_name,
            llm_icon: $message->llm?->icon,
            created_at: $message->created_at->toISOString(),
            agent_question_answer_id: $message->agent_question_answer_id,
            artifacts: is_array($artifacts) ? $artifacts : null,
            source: $message->source,
            read_at: $message->read_at?->toISOString(),
            confidence_score: $message->confidence_score,
            voice: (bool) ($message->metadata['voice'] ?? false),
            trace_id: $message->trace_id,
        );
    }
}

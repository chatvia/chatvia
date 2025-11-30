<?php

namespace App\Data\Resources\Agent\ChatLog;

use App\Models\ChatMessage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogMessageData extends Data
{
    public function __construct(
        public string $id,
        public string $chat_conversation_id,
        public string $content,
        public string $role,
        public ?string $sentiment,
        public ?string $emotion,
        public ?float $emotion_confidence,
        public ?string $feedback,
        public ?bool $is_direct_response,
        public ?int $response_time_ms,
        public ?int $token_count,
        public ?int $prompt_tokens,
        public ?int $completion_tokens,
        public ?string $llm_name,
        public ?string $llm_icon,
        public string $created_at,
        public ?int $agent_question_answer_id,
    ) {}

    public static function fromMessage(ChatMessage $message): self
    {
        return new self(
            id: $message->id,
            chat_conversation_id: $message->chat_conversation_id,
            content: $message->content,
            role: $message->role,
            sentiment: $message->sentiment,
            emotion: $message->emotion,
            emotion_confidence: $message->emotion_confidence,
            feedback: $message->feedback,
            is_direct_response: $message->is_direct_response,
            response_time_ms: $message->response_time_ms,
            token_count: $message->token_count,
            prompt_tokens: $message->prompt_tokens,
            completion_tokens: $message->completion_tokens,
            llm_name: $message->llm?->descriptive_name,
            llm_icon: $message->llm?->icon,
            created_at: $message->created_at->toISOString(),
            agent_question_answer_id: $message->agent_question_answer_id,
        );
    }
}

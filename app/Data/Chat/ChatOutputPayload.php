<?php

namespace App\Data\Chat;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ChatOutputPayload extends Data
{
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
    ) {}
}

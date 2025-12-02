<?php

namespace App\Data\Chat;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ChatOutputPayload extends Data
{
    public function __construct(
        public string $session_id,
        public string $conversation_id,
        public string $message_id,
        public string $message,
        public string $model,
        public Carbon $sent_at,
        public ?string $generation_id = null,
        public ?int $response_time_ms = null,
        public array $sources = [],
        public ?string $language = null,
        public ?float $confidence_score = null,
        public bool $is_direct_response = false,
        public ?int $agent_question_answer_id = null,
        public ?string $source = null,
    ) {}
}

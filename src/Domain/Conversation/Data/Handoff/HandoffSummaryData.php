<?php

namespace Domain\Conversation\Data\Handoff;

use Spatie\LaravelData\Data;

class HandoffSummaryData extends Data
{
    public function __construct(
        public string $conversation_id,
        public string $user_name,
        public ?string $user_email,
        public ?string $last_message,
        public ?string $last_message_at,
        public string $requested_at,
        public int $session_id,
    ) {}
}

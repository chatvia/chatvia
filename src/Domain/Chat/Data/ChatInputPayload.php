<?php

namespace Domain\Chat\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ChatInputPayload extends Data
{
    public function __construct(
        public string $external_session_id,
        public string $external_conversation_id,
        public string $external_message_id,
        public string $model,
        public string $message,
        public Carbon $sent_at,
        public array $updated_message_info = [],
        public ?string $message_for_llm = null,
        public ?int $user_id = null,
        public ?string $language = null,
        public ?string $ip_address = null,
        public ?string $user_agent = null,
        public ?string $referrer_url = null,
        public ?string $page_url = null,
        public ?string $locale = null,
        public ?string $source = null,
    ) {}
}

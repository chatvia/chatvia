<?php

namespace App\Data\Chat;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ChatInputPayload extends Data
{
    public function __construct(
        public string $model,
        public string $message,
        public Carbon $sent_at,
        public array $updated_message_info = [], // TODO: find a better name
        public ?string $message_for_llm = null,
        public ?int $member_id = null,
        public ?string $language = null,
        public ?string $ip_address = null,
        public ?string $user_agent = null,
        public ?string $referrer_url = null,
        public ?string $page_url = null,
        public ?string $locale = null,
        public ?string $source = null,
    ) {}
}

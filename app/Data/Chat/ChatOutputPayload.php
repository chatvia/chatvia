<?php

namespace App\Data\Chat;

use Spatie\LaravelData\Data;

class ChatOutputPayload extends Data
{
    public function __construct(
        public string $message_id,
        public string $role,
        public string $message,
        public string $model,
        public ?string $generation_id,
        public ?float $response_time_ms = null,
        public array $sources = [],
    ) {}
}

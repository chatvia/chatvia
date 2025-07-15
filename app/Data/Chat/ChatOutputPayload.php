<?php

namespace App\Data\Chat;

use Spatie\LaravelData\Data;

class ChatOutputPayload extends Data
{
    public function __construct(
        public string $role,
        public string $message,
        public string $model,
        public ?string $generation_id,
        public ?int $token_count,
        public ?float $response_time_ms = null,
        public array $sources = [],
    ) {}
}

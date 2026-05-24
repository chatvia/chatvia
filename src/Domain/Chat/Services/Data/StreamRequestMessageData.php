<?php

namespace Domain\Chat\Services\Data;

use Domain\Agents\Models\Llm;
use Spatie\LaravelData\Data;

class StreamRequestMessageData extends Data
{
    public function __construct(
        public Llm $model,
        public string $message,
        public ?string $createdAt = null,
    ) {}
}

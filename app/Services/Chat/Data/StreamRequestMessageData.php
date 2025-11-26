<?php

namespace App\Services\Chat\Data;

use App\Models\Llm;
use Spatie\LaravelData\Data;

class StreamRequestMessageData extends Data
{
    public function __construct(
        public Llm $model,
        public string $message,
        public ?string $createdAt = null,
    ) {}
}

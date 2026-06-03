<?php

namespace Domain\Conversation\Services\Data;

use Domain\Llm\Models\Llm;
use Spatie\LaravelData\Data;

class StreamRequestMessageData extends Data
{
    /**
     * @param  array<int, array{id: string, url: string, filename: string, mime_type: string, size_bytes: int}>  $attachments
     */
    public function __construct(
        public Llm $model,
        public string $message,
        public ?string $createdAt = null,
        public array $attachments = [],
    ) {}
}

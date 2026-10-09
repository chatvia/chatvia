<?php

namespace Domain\Conversation\Data\Turn;

use Domain\Llm\Models\Llm;
use Spatie\LaravelData\Data;

/**
 * The visitor's message for one turn: its text, the model that answers it,
 * when it was written (when the client says) and the files attached to it.
 */
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

<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Domain\Conversation\Data\Media\MediaModelPickData;
use Domain\Llm\Models\Llm;
use Spatie\LaravelData\Data;

/**
 * The visitor's message for one turn: its text, the model that answers it,
 * when it was written (when the client says), the files attached to it, and
 * the image and video model picked in the hosted chat (null elsewhere).
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
        public ?MediaModelPickData $mediaModels = null,
    ) {}
}

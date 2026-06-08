<?php

namespace Domain\Conversation\Data\Attachments;

use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Models\Attachment;
use Spatie\LaravelData\Data;

/**
 * Public-shape attachment returned to the widget and hosted chat. `id` is
 * the external_id (UUID) used to claim orphan rows in
 * {@see CreateConversationAction}.
 * `url` is publicly fetchable — Mistral and other URL-consuming services
 * hit it server-side.
 */
class AttachmentData extends Data
{
    public function __construct(
        public string $id,
        public string $url,
        public string $filename,
        public string $mime_type,
        public int $size_bytes,
        public ?int $pages = null,
    ) {}

    public static function fromModel(Attachment $attachment): self
    {
        return new self(
            id: $attachment->external_id,
            url: $attachment->url,
            filename: $attachment->filename,
            mime_type: $attachment->mime_type,
            size_bytes: $attachment->size_bytes,
            pages: $attachment->pages,
        );
    }
}

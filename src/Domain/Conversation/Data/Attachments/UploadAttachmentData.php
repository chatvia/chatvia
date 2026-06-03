<?php

namespace Domain\Conversation\Data\Attachments;

use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * Input DTO for {@see StoreAttachmentAction}.
 *
 * The caller decides:
 *  - the on-disk directory (e.g. `widget-uploads/agent-12/sess-…` or
 *    `chat-uploads/agent-12/conv-…`) so the action stays neutral about
 *    which surface uploaded the file;
 *  - the conversation FK, or null for Home.vue uploads that happen before
 *    the conversation row exists (claimed by
 *    {@see CreateConversationAction}).
 */
class UploadAttachmentData extends Data
{
    public function __construct(
        public UploadedFile $file,
        public string $directory,
        public ?int $conversationId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Attachments;

use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Services\ChatStoragePath;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * Input DTO for {@see StoreAttachmentAction}.
 *
 * The caller decides:
 *  - the chat folder ({@see ChatStoragePath}): the conversation's own, or
 *    the agent's `pending` folder before the conversation exists. The action
 *    adds the kind subfolder (`images`, `files`…) and stays neutral about
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
        public int $workspaceId,
        public ?int $conversationId = null,
    ) {}
}

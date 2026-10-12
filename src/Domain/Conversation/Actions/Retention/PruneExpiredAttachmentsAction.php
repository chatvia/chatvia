<?php

namespace Domain\Conversation\Actions\Retention;

use Domain\Conversation\Models\Attachment;

/**
 * Deletes uploads that never joined a conversation, and their stored files
 * with them, once they are `$orphanDays` old. The files of a conversation go
 * with the conversation: its delete hook removes them when it is deleted (the
 * retention sweep deletes it at its horizon), and the trash purge removes any
 * a bulk delete left behind. Rows are deleted one by one through the model,
 * so its delete hook removes the stored object — no row is ever left pointing
 * at a deleted file. chat_attachments_orphan_idx finds them.
 */
class PruneExpiredAttachmentsAction
{
    /**
     * @return int how many attachments were deleted
     */
    public function execute(int $orphanDays, int $chunk): int
    {
        $deleted = 0;

        Attachment::query()
            ->whereNull('chat_conversation_id')
            ->where('created_at', '<', now()->subDays($orphanDays))
            ->orderBy('id')
            ->chunkById($chunk, function ($attachments) use (&$deleted): void {
                foreach ($attachments as $attachment) {
                    $attachment->delete();
                    $deleted++;
                }
            });

        return $deleted;
    }
}

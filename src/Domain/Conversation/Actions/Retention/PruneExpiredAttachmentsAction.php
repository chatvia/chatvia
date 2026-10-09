<?php

namespace Domain\Conversation\Actions\Retention;

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes chat attachments that no longer belong to a live conversation, and
 * their stored files with them. A file lives exactly as long as its
 * conversation: one in a conversation past its retention horizon (or already
 * deleted) goes, and an upload that never joined a conversation goes after
 * `$orphanDays`. Rows are deleted one by one through the model, so its delete
 * hook removes the stored object — no row is ever left pointing at a deleted
 * file.
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
            ->where(function (Builder $query) use ($orphanDays): void {
                $query
                    ->where(fn (Builder $orphans) => $orphans
                        ->whereNull('chat_conversation_id')
                        ->where('created_at', '<', now()->subDays($orphanDays)))
                    ->orWhereHas('conversation', fn (ConversationQueryBuilder $conversation) => $conversation
                        ->withTrashed()
                        ->where(fn (Builder $gone) => $gone
                            ->whereNotNull('deleted_at')
                            ->orWhere('expires_at', '<', now())));
            })
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

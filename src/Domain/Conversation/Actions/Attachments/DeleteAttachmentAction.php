<?php

namespace Domain\Conversation\Actions\Attachments;

use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\DB;

/**
 * Removes a composer-uploaded attachment atomically. The `chat_message_id IS
 * NULL` predicate inside a `SELECT FOR UPDATE` closes the race against
 * {@see StoreUserMessageAction}'s sweep,
 * which claims every orphan attachment on a conversation by id (not by
 * payload). Without the lock, a quick X-then-send sequence could leave the
 * row deleted server-side AND attached to the brand-new message.
 *
 * Returns `false` when the row was bound to a sent message between the
 * caller's authorization check and the lock acquisition — the caller should
 * surface that as a 403.
 */
class DeleteAttachmentAction
{
    public function execute(Attachment $attachment): bool
    {
        return DB::transaction(function () use ($attachment): bool {
            $fresh = Attachment::query()
                ->where('id', $attachment->id)
                ->whereNull('chat_message_id')
                ->lockForUpdate()
                ->first();

            if ($fresh === null) {
                return false;
            }

            // Fires the model's `deleting` hook, which wipes the underlying
            // S3 object on the public Scaleway bucket.
            $fresh->delete();

            return true;
        });
    }
}

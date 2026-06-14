<?php

namespace Domain\Conversation\Models\Concerns;

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;

/**
 * Shared body for the attachment S3-cascade that both {@see Conversation}
 * and {@see Message} run from their own `deleting`
 * hook.
 *
 * The DB-level cascade on the `chat_attachments` foreign keys removes rows but
 * skips Eloquent events — so the S3 objects would leak. Triggering the
 * attachment deletes through Eloquent first fires the {@see Attachment}
 * `deleting` observer that wipes the underlying file.
 *
 * Only the duplicated body lives here; each owning model keeps its own
 * explicit `booted()`/`deleting` registration so the "deliberately explicit"
 * intent (every attachment owner repeats the hook) stays visible at the call
 * site. The trait removes the copy-paste, not the registration.
 */
trait CascadesAttachmentDeletes
{
    /**
     * Delete every attached row through Eloquent so each fires its own
     * `deleting` observer and wipes the backing S3 object.
     *
     * Relies on the consuming model exposing an `attachments()` relation.
     */
    public function cascadeAttachmentDeletes(): void
    {
        $this->attachments()->each(static fn (Attachment $a) => $a->delete());
    }
}

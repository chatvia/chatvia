<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Requests\Chat\DeleteAttachmentRequest;
use Domain\Conversation\Actions\Attachments\DeleteAttachmentAction;
use Domain\Conversation\Models\Attachment;
use Illuminate\Http\Response;

/**
 * Removes a composer-uploaded attachment when the chatter clicks the X on
 * the chip. Authorization lives in {@see DeleteAttachmentRequest}; the
 * action wraps the delete in a transaction + row lock so we can't race
 * StoreUserMessageAction's orphan sweep. A `false` return from the action
 * means the row was bound to a sent message between authorize() and the
 * lock acquisition — surface as 403, same as a stale check.
 */
class AttachmentDeleteController
{
    public function __invoke(
        DeleteAttachmentRequest $request,
        Attachment $attachment,
        DeleteAttachmentAction $action,
    ): Response {
        if (! $action->execute($attachment)) {
            abort(403);
        }

        return response()->noContent();
    }
}

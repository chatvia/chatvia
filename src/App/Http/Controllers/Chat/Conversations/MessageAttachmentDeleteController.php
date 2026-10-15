<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Controllers\Chat\Messages\AttachmentDeleteController;
use App\Http\Middleware\Chat\EnsureChatIsAccessible;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Illuminate\Http\Response;

/**
 * Removes an attachment that's already bound to a sent message. Distinct
 * from {@see AttachmentDeleteController},
 * which handles the composer-stage (unsent) case under different rules.
 *
 * Authorization comes from {@see EnsureChatIsAccessible}:
 * the route carries `{conversationId}` so the middleware enforces
 * `ChatCache::isOwnedBy()` for authenticated chatters on protected agents.
 * The inline check below is a defense-in-depth tie between the URL's
 * conversation id, the row's conversation, and the request's resolved agent
 * so a captured external_id from another conversation/agent can't be wiped
 * via this endpoint.
 *
 * The deleting hook on {@see Attachment::booted()} wipes the underlying S3
 * object. Later turns no longer send the file to the document LLM — that's
 * the UX the user confirmed in the AlertDialog ("the assistant won't have
 * access anymore").
 */
class MessageAttachmentDeleteController
{
    public function __invoke(string $conversationId, Attachment $attachment, #[HostedChatAgent] Agent $agent): Response
    {
        $conversation = $attachment->conversation;
        if ($conversation === null
            || $conversation->external_id !== $conversationId
            || $conversation->session?->agent_id !== $agent->id
        ) {
            abort(403);
        }

        $attachment->delete();

        return response()->noContent();
    }
}

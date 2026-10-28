<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Controllers\Chat\Messages\AttachmentDeleteController;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Response;

/**
 * Removes an attachment that's already bound to a sent message. Distinct
 * from {@see AttachmentDeleteController},
 * which handles the composer-stage (unsent) case under different rules.
 *
 * The conversation must be in the CALLER's chat session of this agent, like
 * every other conversation route, so nobody can wipe another visitor's file
 * by its ids, on a public agent included. The address, the file's row and
 * the request's agent must all name the same conversation.
 *
 * The deleting hook on {@see Attachment::booted()} wipes the underlying S3
 * object. Later turns no longer send the file to the document LLM — that's
 * the UX the user confirmed in the AlertDialog ("the assistant won't have
 * access anymore").
 */
class MessageAttachmentDeleteController
{
    public function __invoke(
        string $conversationId,
        Attachment $attachment,
        #[HostedChatAgent] Agent $agent,
        HostedChatSession $session,
    ): Response {
        Conversation::query()
            ->inSession($session->find($agent) ?? abort(404), $agent->id)
            ->where('external_id', $conversationId)
            ->whereKey($attachment->chat_conversation_id)
            ->firstOrFail();

        $attachment->delete();

        return response()->noContent();
    }
}

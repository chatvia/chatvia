<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\Concerns\ValidatesChatAttachment;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatStoragePath;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a file uploaded from the hosted-chat composer and decides where
 * it is stored.
 */
class UploadAttachmentRequest extends FormRequest
{
    use ValidatesChatAttachment;

    /**
     * @param  Agent  $agent  The agent the hosted chat resolved for this
     *                        request — the authoritative identity, not the
     *                        client-supplied X-AGENT-ID header.
     * @param  Conversation|null  $conversation  the caller's own conversation, or
     *                                           null on the home screen: the file
     *                                           then waits in the agent's pending
     *                                           folder ({@see ChatStoragePath})
     */
    public function toData(Agent $agent, ?Conversation $conversation): UploadAttachmentData
    {
        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: $conversation instanceof Conversation
                ? ChatStoragePath::forConversation($agent, $conversation->external_id)
                : ChatStoragePath::pending($agent),
            workspaceId: $agent->workspace_id,
            conversationId: $conversation?->id,
        );
    }
}

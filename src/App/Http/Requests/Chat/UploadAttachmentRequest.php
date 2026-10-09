<?php

namespace App\Http\Requests\Chat;

use App\Http\Chat\HostedChatAgent;
use App\Http\Requests\Concerns\ValidatesPdfAttachment;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a PDF uploaded from the hosted-chat composer and decides where
 * it is stored.
 */
class UploadAttachmentRequest extends FormRequest
{
    use ValidatesPdfAttachment;

    /**
     * The workspace of the agent the hosted chat resolved for this request —
     * never a client-supplied header, so a visitor cannot borrow another
     * workspace's message balance to pass the PDF credit check.
     */
    protected function workspaceUserId(): ?int
    {
        return HostedChatAgent::from($this)?->user_id;
    }

    /**
     * @param  Agent|null  $agent  The agent the hosted chat resolved for this
     *                             request — the authoritative identity, not the
     *                             client-supplied X-AGENT-ID header. Storage is
     *                             entity-first (users/{workspace}/agents/{agent}),
     *                             so deleting a workspace or agent is one prefix wipe.
     */
    public function toData(?Agent $agent, ?int $conversationId): UploadAttachmentData
    {
        // Sanitize the client-supplied conversation id before it becomes a
        // storage path segment — block `../` cross-prefix writes (see the widget
        // UploadAttachmentRequest).
        $conversationExternalId = preg_replace('/[^A-Za-z0-9-]/', '', (string) $this->header('X-CONVERSATION-ID')) ?: 'pending';

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf(
                'users/%s/agents/%s/attachments/%s',
                $agent?->workspaceExternalId() ?? '',
                $agent?->external_id ?? '',
                $conversationExternalId,
            ),
            conversationId: $conversationId,
        );
    }
}

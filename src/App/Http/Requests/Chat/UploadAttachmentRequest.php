<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\Concerns\ValidatesPdfAttachment;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    use ValidatesPdfAttachment;

    protected function workspaceUserId(): ?int
    {
        return Agent::query()
            ->whereKey((int) ($this->header('X-AGENT-ID') ?: 0))
            ->value('user_id');
    }

    public function toData(?int $conversationId): UploadAttachmentData
    {
        $agentId = (int) ($this->header('X-AGENT-ID') ?: 0);
        // Sanitize the client-supplied conversation id before it becomes a
        // storage path segment — block `../` cross-prefix writes (see the widget
        // UploadAttachmentRequest).
        $conversationExternalId = preg_replace('/[^A-Za-z0-9-]/', '', (string) $this->header('X-CONVERSATION-ID')) ?: 'pending';

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf('chat-uploads/agent-%d/%s', $agentId, $conversationExternalId),
            conversationId: $conversationId,
        );
    }
}

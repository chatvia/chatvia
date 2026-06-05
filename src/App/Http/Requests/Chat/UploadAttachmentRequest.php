<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\Concerns\ValidatesPdfAttachment;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    use ValidatesPdfAttachment;

    public function toData(?int $conversationId): UploadAttachmentData
    {
        $agentId = (int) ($this->header('X-AGENT-ID') ?: 0);
        $conversationExternalId = (string) ($this->header('X-CONVERSATION-ID') ?: 'pending');

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf('chat-uploads/agent-%d/%s', $agentId, $conversationExternalId),
            conversationId: $conversationId,
        );
    }
}

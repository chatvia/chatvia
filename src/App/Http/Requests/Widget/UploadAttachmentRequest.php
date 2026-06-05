<?php

namespace App\Http\Requests\Widget;

use App\Http\Requests\Concerns\ValidatesPdfAttachment;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    use ValidatesPdfAttachment;

    public function toData(Agent $agent, ?int $conversationId): UploadAttachmentData
    {
        $sessionId = (string) $this->header('X-SESSION-ID');

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf('widget-uploads/agent-%d/%s', $agent->id, $sessionId),
            conversationId: $conversationId,
        );
    }
}

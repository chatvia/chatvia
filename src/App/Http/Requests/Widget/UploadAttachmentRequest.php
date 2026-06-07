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
        // The session id is a client-supplied header that becomes a storage path
        // segment. Strip anything outside the UUID alphabet so `../` (which
        // Flysystem's normaliser would collapse into another agent's prefix)
        // can never reach the path.
        $sessionId = preg_replace('/[^A-Za-z0-9-]/', '', (string) $this->header('X-SESSION-ID')) ?? '';

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf('widget-uploads/agent-%d/%s', $agent->id, $sessionId),
            conversationId: $conversationId,
        );
    }
}

<?php

namespace App\Http\Requests\Widget;

use App\Http\Requests\Concerns\ValidatesPdfAttachment;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Widget\WidgetHeaders;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * A file a widget visitor attaches to their conversation (PDF only for now).
 * Validation is shared with the hosted chat ({@see ValidatesPdfAttachment});
 * the file is stored under the agent's attachment folder for the visitor's
 * session.
 */
class UploadAttachmentRequest extends FormRequest
{
    use ValidatesPdfAttachment;

    public function toData(Agent $agent, ?int $conversationId): UploadAttachmentData
    {
        // The session id is a client-supplied header that becomes a storage path
        // segment. Strip anything outside the UUID alphabet so `../` (which
        // Flysystem's normaliser would collapse into another agent's prefix)
        // can never reach the path.
        $sessionId = Str::replaceMatches('/[^A-Za-z0-9-]/', '', (string) $this->header(WidgetHeaders::SESSION_ID)) ?? '';

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf(
                'workspaces/%s/agents/%s/attachments/%s',
                $agent->workspaceExternalId(),
                $agent->external_id,
                $sessionId,
            ),
            workspaceId: $agent->workspace_id,
            conversationId: $conversationId,
        );
    }
}

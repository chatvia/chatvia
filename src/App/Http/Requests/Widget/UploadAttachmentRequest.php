<?php

namespace App\Http\Requests\Widget;

use App\Http\Requests\Concerns\ValidatesChatAttachment;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatStoragePath;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A file a widget visitor attaches to their conversation (a PDF or an image).
 * Validation is shared with the hosted chat ({@see ValidatesChatAttachment});
 * the file is stored in the conversation's chat folder ({@see ChatStoragePath}).
 */
class UploadAttachmentRequest extends FormRequest
{
    use ValidatesChatAttachment;

    /**
     * @param  Conversation  $conversation  already scoped to this agent and the
     *                                      visitor's session, so the folder never
     *                                      comes from a client header
     */
    public function toData(Agent $agent, Conversation $conversation): UploadAttachmentData
    {
        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: ChatStoragePath::forConversation($agent, $conversation->external_id),
            workspaceId: $agent->workspace_id,
            conversationId: $conversation->id,
        );
    }
}

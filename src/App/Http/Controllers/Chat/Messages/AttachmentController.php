<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Requests\Chat\UploadAttachmentRequest;
use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;

class AttachmentController
{
    public function __invoke(
        UploadAttachmentRequest $request,
        StoreAttachmentAction $action,
    ): JsonResponse {
        // Resolve the conversation FK from the external id header. 'pending'
        // means the visitor is still on Home.vue (no conversation yet) —
        // we persist an orphan row that CreateConversationAction will claim
        // by external_id once the conversation is created.
        $conversationExternalId = (string) ($request->header('X-CONVERSATION-ID') ?: 'pending');
        $conversationId = null;
        if ($conversationExternalId !== 'pending') {
            $conversationId = Conversation::query()
                ->where('external_id', $conversationExternalId)
                ->value('id');
        }

        $attachment = $action->execute($request->toData($conversationId));

        return response()->json(['data' => AttachmentData::fromModel($attachment)]);
    }
}

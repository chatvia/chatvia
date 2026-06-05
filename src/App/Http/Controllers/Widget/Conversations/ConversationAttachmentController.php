<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Requests\Widget\UploadAttachmentRequest;
use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;

class ConversationAttachmentController
{
    public function __invoke(
        UploadAttachmentRequest $request,
        WidgetVerification $verification,
        StoreAttachmentAction $action,
    ): JsonResponse {
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->header('X-CONVERSATION-ID');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing thread/session ID'], 400);
        }

        $agent = current_agent_from_widget();

        // Verifier confirms
        // it belongs to this agent so a forged X-CONVERSATION-ID can't bind an
        // upload to someone else's conversation.
        $conversation = $verification->verifyConversationId($conversationId, $agent);
        abort_if(! $conversation, 403, 'Invalid thread');

        $attachment = $action->execute($request->toData($agent, $conversation->id));

        return response()->json(['data' => AttachmentData::fromModel($attachment)]);
    }
}

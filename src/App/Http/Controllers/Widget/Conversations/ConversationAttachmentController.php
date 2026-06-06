<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Requests\Widget\UploadAttachmentRequest;
use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Data\Attachments\AttachmentData;
use Illuminate\Http\JsonResponse;

class ConversationAttachmentController
{
    public function __invoke(
        UploadAttachmentRequest $request,
        FindWidgetConversationAction $findConversation,
        StoreAttachmentAction $action,
    ): JsonResponse {
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->header('X-CONVERSATION-ID');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing thread/session ID'], 400);
        }

        $agent = current_agent_from_widget();

        // Scope to (agent, requesting session) so a forged X-CONVERSATION-ID
        // can't bind an upload to another visitor's conversation on this agent.
        $conversation = $findConversation->execute((string) $conversationId, (string) $sessionId, $agent);
        abort_if(! $conversation, 403, 'Invalid thread');

        $attachment = $action->execute($request->toData($agent, $conversation->id));

        return response()->json(['data' => AttachmentData::fromModel($attachment)]);
    }
}

<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Widget\UploadAttachmentRequest;
use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Data\Attachments\AttachmentData;
use Illuminate\Http\JsonResponse;

/**
 * Stores a file a widget visitor attaches to their conversation and returns
 * its public URL, which the model-side tools (PDF reader, …) fetch.
 */
class ConversationAttachmentController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        UploadAttachmentRequest $request,
        FindWidgetConversationAction $findConversation,
        StoreAttachmentAction $action,
    ): JsonResponse {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $this->widgetConversationId($request);

        if ($sessionId === null || $conversationId === null) {
            return response()->json(['error' => 'Missing thread/session ID'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $agent = $this->widgetAgent();

        // Scoped to this agent and session, so a forged conversation id cannot
        // attach an upload to another visitor's conversation.
        $conversation = $findConversation->execute($conversationId, $sessionId, $agent);
        abort_if(! $conversation, JsonResponse::HTTP_FORBIDDEN, 'Invalid thread');

        $attachment = $action->execute($request->toData($agent, $conversation->id));

        return response()->json(['data' => AttachmentData::fromModel($attachment)]);
    }
}

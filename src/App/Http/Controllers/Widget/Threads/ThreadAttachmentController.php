<?php

namespace App\Http\Controllers\Widget\Threads;

use App\Http\Requests\Widget\UploadAttachmentRequest;
use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;

class ThreadAttachmentController
{
    public function __invoke(
        UploadAttachmentRequest $request,
        WidgetVerification $verification,
        StoreAttachmentAction $action,
    ): JsonResponse {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        if (! $sessionId || ! $threadId) {
            return response()->json(['error' => 'Missing thread/session ID'], 400);
        }

        $agent = current_agent_from_widget();

        // Thread = conversation in widget terminology. The verifier confirms
        // it belongs to this agent so a forged X-THREAD-ID can't bind an
        // upload to someone else's conversation.
        $conversation = $verification->verifyThreadId($threadId, $agent);
        abort_if(! $conversation, 403, 'Invalid thread');

        $attachment = $action->execute($request->toData($agent, $conversation->id));

        return response()->json(['data' => AttachmentData::fromModel($attachment)]);
    }
}

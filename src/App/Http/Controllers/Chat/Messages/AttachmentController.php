<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\UploadAttachmentRequest;
use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;

/**
 * Uploads a file into a hosted-chat conversation from the composer.
 */
class AttachmentController
{
    /** Conversation id the client sends while the visitor is still on the home screen. */
    private const string PENDING_CONVERSATION = 'pending';

    public function __invoke(
        UploadAttachmentRequest $request,
        StoreAttachmentAction $action,
        HostedChatSession $session,
    ): JsonResponse {
        $attachment = $action->execute($request->toData($this->conversationId($request, $session)));

        return response()->json(['data' => AttachmentData::fromModel($attachment)]);
    }

    /**
     * The conversation's key, from the external id header. `pending` means the
     * visitor is still on the home screen (no conversation yet): the row is
     * stored without one, and the conversation claims it when it is created.
     * Only a conversation in the caller's own session is used.
     */
    private function conversationId(UploadAttachmentRequest $request, HostedChatSession $session): ?int
    {
        $externalId = (string) ($request->header('X-CONVERSATION-ID') ?: self::PENDING_CONVERSATION);
        $agent = current_agent();

        if ($externalId === self::PENDING_CONVERSATION || $agent === null) {
            return null;
        }

        $sessionId = $session->find($agent);

        if ($sessionId === null) {
            return null;
        }

        return Conversation::query()
            ->inSession($sessionId, $agent->id)
            ->where('external_id', $externalId)
            ->value('id');
    }
}

<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\UploadAttachmentRequest;
use Domain\Agent\Models\Agent;
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
        #[HostedChatAgent] Agent $agent,
        StoreAttachmentAction $action,
        HostedChatSession $session,
    ): JsonResponse {
        $attachment = $action->execute($request->toData($agent, $this->conversation($request, $agent, $session)));

        return response()->json(['data' => AttachmentData::fromModel($attachment)]);
    }

    /**
     * The conversation, from the external id header. `pending` means the
     * visitor is still on the home screen (no conversation yet): the row is
     * stored without one, and the conversation claims it when it is created.
     * Only a conversation in the caller's own session is used.
     */
    private function conversation(UploadAttachmentRequest $request, Agent $agent, HostedChatSession $session): ?Conversation
    {
        $externalId = $request->header('X-CONVERSATION-ID') ?: self::PENDING_CONVERSATION;

        if ($externalId === self::PENDING_CONVERSATION) {
            return null;
        }

        $sessionId = $session->find($agent);

        if ($sessionId === null) {
            return null;
        }

        return Conversation::query()
            ->inSession($sessionId, $agent->id)
            ->where('external_id', $externalId)
            ->first();
    }
}

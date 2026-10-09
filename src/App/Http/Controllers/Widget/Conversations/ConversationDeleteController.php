<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\ClearConversationForVisitorAction;
use Domain\Conversation\Actions\Removal\ClearSessionConversationsForVisitorAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Auth\WidgetAckData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The widget's "clear chat" (one conversation, named by X-CONVERSATION-ID) and
 * "clear all" (no conversation header). This is a view reset, not erasure: the
 * conversation stays in the customer's chat log. Only the owner/admin delete
 * path (AdminDeleteConversationAction) erases content.
 */
class ConversationDeleteController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        WidgetVerification $widgetVerification,
        ClearConversationForVisitorAction $clearConversation,
        ClearSessionConversationsForVisitorAction $clearSession,
    ): JsonResponse|WidgetAckData {
        $sessionId = $this->widgetSessionId($request);

        if ($sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $widgetUser = $this->widgetUser($request);
        $conversationId = $this->widgetConversationId($request);

        // On a protected agent only the chatter who owns the session may clear
        // its threads — including "clear all" — so a replayed session id
        // cannot wipe another signed-in visitor's history.
        if ($widgetUser instanceof User && ! $widgetVerification->verifySessionExternalId($sessionId, $agent, $widgetUser)) {
            return response()->json(['error' => 'Invalid session'], JsonResponse::HTTP_NOT_FOUND);
        }

        $scope = new ChatScopeData($agent, $sessionId);

        if ($conversationId === null) {
            $clearSession->execute($scope);

            return new WidgetAckData;
        }

        // The conversation must belong to THIS agent (and, on a protected
        // agent, to the signed-in chatter), so faking X-AGENT-ID or
        // X-SESSION-ID cannot clear a conversation of another agent or chatter.
        if (! $widgetVerification->verifyConversationId($conversationId, $agent, $widgetUser) instanceof Conversation) {
            return response()->json(['error' => 'Invalid thread'], JsonResponse::HTTP_NOT_FOUND);
        }

        $clearConversation->execute($scope->forConversation($conversationId));

        return new WidgetAckData;
    }
}

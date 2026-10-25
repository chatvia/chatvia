<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Removal\ClearConversationForVisitorAction;
use Domain\Conversation\Actions\Removal\ClearSessionConversationsForVisitorAction;
use Domain\Conversation\Actions\Removal\DeleteConversationAction;
use Domain\Conversation\Actions\Removal\DeleteSessionConversationsAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Auth\WidgetAckData;
use Domain\Widget\Enums\ChatDeletionMode;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The widget's "remove chat" (one conversation, named by X-CONVERSATION-ID) and
 * "remove all" (no conversation header). The install's ChatDeletionMode decides
 * what that means: `end` refuses (the visitor can only start a new chat), `hide`
 * is a view reset that keeps the conversation in the customer's chat log, and
 * `delete` erases it as the owner's delete does.
 */
class ConversationDeleteController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        WidgetVerification $widgetVerification,
        FindWidgetConversationAction $findConversation,
        ClearConversationForVisitorAction $clearConversation,
        ClearSessionConversationsForVisitorAction $clearSession,
        DeleteConversationAction $deleteConversation,
        DeleteSessionConversationsAction $deleteSession,
    ): JsonResponse|WidgetAckData {
        $sessionId = $this->widgetSessionId($request);

        if ($sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // The widget hides the action in this mode; a direct call is refused
        // here, so the admin's choice to keep every chat holds.
        $mode = ChatDeletionMode::inUse();

        if ($mode === ChatDeletionMode::End) {
            return response()->json(['error' => 'Removing chats is turned off'], JsonResponse::HTTP_FORBIDDEN);
        }

        $viewer = $this->widgetViewer($request);
        $conversationId = $this->widgetConversationId($request);

        // On a protected agent only the chatter who owns the session may clear
        // its threads — including "clear all" — so a replayed session id
        // cannot wipe another signed-in visitor's history.
        if ($viewer instanceof ChatViewerData && ! $widgetVerification->verifySessionExternalId($sessionId, $agent, $viewer)) {
            return response()->json(['error' => 'Invalid session'], JsonResponse::HTTP_NOT_FOUND);
        }

        $scope = new ChatScopeData($agent, $sessionId);

        if ($conversationId === null) {
            $mode === ChatDeletionMode::Delete
                ? $deleteSession->execute($scope, Conversation::DELETED_BY_VISITOR)
                : $clearSession->execute($scope);

            return new WidgetAckData;
        }

        // The conversation must belong to THIS agent, THIS session and, on a
        // protected agent, the signed-in chatter, so faking X-AGENT-ID or
        // X-SESSION-ID cannot clear a conversation of another agent, visitor
        // or chatter.
        if (! $findConversation->execute($conversationId, $sessionId, $agent, $viewer) instanceof Conversation) {
            return response()->json(['error' => 'Invalid thread'], JsonResponse::HTTP_NOT_FOUND);
        }

        $mode === ChatDeletionMode::Delete
            ? $deleteConversation->execute($scope->forConversation($conversationId), Conversation::DELETED_BY_VISITOR)
            : $clearConversation->execute($scope->forConversation($conversationId));

        return new WidgetAckData;
    }
}

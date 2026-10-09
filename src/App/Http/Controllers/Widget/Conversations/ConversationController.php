<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Widget\Actions\EnsureWidgetConversationAction;
use Domain\Widget\Data\Conversation\WidgetConversationsResultData;
use Domain\Widget\Queries\ListWidgetConversationsQuery;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lists the visitor's conversations when the widget opens. A session with no
 * conversation gets a new one first, so the widget always has a thread to show.
 */
class ConversationController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        WidgetVerification $widgetVerification,
        EnsureWidgetConversationAction $ensureConversation,
        ListWidgetConversationsQuery $conversations,
    ): JsonResponse|WidgetConversationsResultData {
        $sessionId = $this->widgetSessionId($request);

        if ($sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $widgetUser = $this->widgetUser($request);

        // On a protected agent, only list a session that belongs to the
        // signed-in chatter: another chatter's replayed session id shows nothing.
        if ($widgetUser instanceof User && ! $widgetVerification->verifySessionExternalId($sessionId, $agent, $widgetUser)) {
            return new WidgetConversationsResultData(conversations: []);
        }

        $ensureConversation->execute($agent, $sessionId);

        return new WidgetConversationsResultData(conversations: $conversations->get($agent, $sessionId));
    }
}

<?php

namespace App\Http\Controllers\Widget\Conversations;

use Domain\Widget\Actions\ListWidgetConversationsAction;
use Domain\Widget\Data\Conversation\WidgetConversationsResultData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;

class ConversationController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ListWidgetConversationsAction $list): WidgetConversationsResultData
    {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return new WidgetConversationsResultData(conversations: []);
        }

        $agent = current_agent_from_widget();
        $widgetUser = $request->attributes->get('widget_user');
        $sessionId = (string) $request->header('X-SESSION-ID');

        // On a protected agent, only list conversations for a session that
        // belongs to the authenticated chatter — a replayed session id from
        // another signed-in visitor resolves to nothing.
        if ($widgetUser && ! $widgetVerification->verifySessionExternalId($sessionId, $agent, $widgetUser)) {
            return new WidgetConversationsResultData(conversations: []);
        }

        return $list->execute($agent, $sessionId);
    }
}

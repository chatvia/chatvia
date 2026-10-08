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
        $agent = current_agent_from_widget();
        abort_if($agent === null, 404);
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

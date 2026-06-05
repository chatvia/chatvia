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

        return $list->execute(current_agent_from_widget(), (string) $request->header('X-SESSION-ID'));
    }
}

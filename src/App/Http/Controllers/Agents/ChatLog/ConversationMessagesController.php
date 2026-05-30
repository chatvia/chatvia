<?php

namespace App\Http\Controllers\Agents\ChatLog;

use Domain\Agents\Actions\ListConversationMessagesAction;
use Domain\Agents\Data\Resources\ChatLog\ChatLogMessagesResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\Request;

/**
 * Paginated message list for a single chat-log conversation. The action also
 * fills in handoff state + the attached lead row.
 */
class ConversationMessagesController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        ChatConversation $conversation,
        ListConversationMessagesAction $list,
    ): ChatLogMessagesResourceData {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);
        abort_if($conversation->session->agent_id !== $agent->id, 404);

        return $list->execute($agent, $conversation, $request->integer('page', 1));
    }
}

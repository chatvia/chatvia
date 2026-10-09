<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Actions\ChatLog\ListConversationMessagesAction;
use Domain\Agent\Data\Resources\ChatLog\Messages\ChatLogMessagesResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Request;

/**
 * Paginated message list for a single chat-log conversation. The action also
 * fills in handoff state + the attached lead row.
 */
class ConversationMessageController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        Conversation $conversation,
        ListConversationMessagesAction $list,
    ): ChatLogMessagesResourceData {
        abort_unless($conversation->belongsToAgent($agent), 404);

        return $list->execute($agent, $conversation, $request->integer('page', 1));
    }
}

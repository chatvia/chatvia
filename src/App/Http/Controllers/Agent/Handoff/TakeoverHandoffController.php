<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Resources\Handoff\HandoffTakeoverResultData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\TakeoverConversationAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Request;

/**
 * An operator takes a conversation over from the AI. Agent access and the
 * `agent.chat.takeover` permission are checked by route middleware.
 */
class TakeoverHandoffController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        Conversation $conversation,
        TakeoverConversationAction $takeover,
    ): HandoffTakeoverResultData {
        abort_unless($conversation->belongsToAgent($agent), 403, __('Unauthorized.'));

        abort_unless(
            $takeover->execute($conversation, $request->user()),
            409,
            __('Unable to take over this conversation. It may already be handled by another admin.'),
        );

        return HandoffTakeoverResultData::fromConversation($conversation);
    }
}

<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Requests\Agent\Handoff\AgentHandoffTypingRequest;
use App\Http\Resources\Handoff\HandoffTypingResultData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\BroadcastChatTypingAction;
use Domain\Conversation\Models\Conversation;

/**
 * Broadcasts the operator's typing indicator to the visitor. Agent access is
 * checked by the route's `can.access.agent` middleware.
 */
class HandoffTypingController
{
    public function __invoke(
        AgentHandoffTypingRequest $request,
        Agent $agent,
        Conversation $conversation,
        BroadcastChatTypingAction $broadcastTyping,
    ): HandoffTypingResultData {
        abort_unless($conversation->belongsToAgent($agent), 403, __('Unauthorized.'));

        abort_unless(
            $conversation->isHeldBy($request->user()),
            403,
            __('You must take over this conversation before sending typing indicators.'),
        );

        $broadcastTyping->execute($request->toData($conversation));

        return new HandoffTypingResultData;
    }
}

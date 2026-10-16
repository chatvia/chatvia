<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Requests\Agent\Handoff\AgentHandoffTypingRequest;
use App\Http\Resources\Handoff\HandoffTypingResultData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\SendHandoffTypingAction;
use Domain\Conversation\Models\Conversation;

/**
 * Broadcasts the operator's typing indicator to the visitor. Agent access and
 * the conversation belonging to the agent are checked by the route's
 * `can.access.agent` middleware; holding the conversation by the Action.
 */
class HandoffTypingController
{
    public function __invoke(
        AgentHandoffTypingRequest $request,
        Agent $agent,
        Conversation $conversation,
        SendHandoffTypingAction $sendTyping,
    ): HandoffTypingResultData {
        $sendTyping->execute($conversation, $request->user(), $request->toData($conversation));

        return new HandoffTypingResultData;
    }
}

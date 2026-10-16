<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Requests\Agent\Handoff\AgentHandoffMessageRequest;
use App\Http\Resources\Handoff\HandoffMessageResultData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\SendHandoffAdminMessageAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;

/**
 * The operator who holds a conversation replies to the visitor. Agent access
 * and the conversation belonging to the agent are checked by the route's
 * `can.access.agent` middleware; holding the conversation by the Action.
 */
class SendHandoffMessageController
{
    public function __invoke(
        AgentHandoffMessageRequest $request,
        Agent $agent,
        Conversation $conversation,
        SendHandoffAdminMessageAction $sendMessage,
    ): HandoffMessageResultData {
        $message = $sendMessage->execute($request->toData($conversation));

        abort_if(! $message instanceof Message, 500, __('Failed to send message.'));

        return HandoffMessageResultData::fromMessage($message);
    }
}

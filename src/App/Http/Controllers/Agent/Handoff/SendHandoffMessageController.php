<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Requests\Agent\Handoff\AgentHandoffMessageRequest;
use App\Http\Resources\Handoff\HandoffMessageResultData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\SendHandoffAdminMessageAction;
use Domain\Conversation\Models\Conversation;

/**
 * The operator who holds a conversation replies to the visitor. Agent access
 * is checked by the route's `can.access.agent` middleware.
 */
class SendHandoffMessageController
{
    public function __invoke(
        AgentHandoffMessageRequest $request,
        Agent $agent,
        Conversation $conversation,
        SendHandoffAdminMessageAction $sendMessage,
    ): HandoffMessageResultData {
        abort_unless($conversation->belongsToAgent($agent), 403, __('Unauthorized.'));

        abort_unless(
            $conversation->isHeldBy($request->user()),
            403,
            __('You must take over this conversation before sending messages.'),
        );

        $message = $sendMessage->execute($request->toData($conversation));

        abort_if($message === null, 500, __('Failed to send message.'));

        return HandoffMessageResultData::fromMessage($message);
    }
}

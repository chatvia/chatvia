<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\DeleteMessageRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\DeleteMessagePairAction;

/**
 * Removes a message and the one before it from the hosted-chat visitor's
 * live history.
 */
class MessageDeleteController
{
    public function __invoke(
        DeleteMessageRequest $request,
        #[HostedChatAgent] Agent $agent,
        DeleteMessagePairAction $deletePair,
        HostedChatSession $session,
    ): void {
        $deletePair->execute(
            $session->scope($agent, $request->conversationId()),
            $request->messageId(),
        );
    }
}

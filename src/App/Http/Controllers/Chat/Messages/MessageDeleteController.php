<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\DeleteMessageRequest;
use Domain\Conversation\Actions\Messages\DeleteMessagePairAction;

/**
 * Removes a message and the one before it from the hosted-chat visitor's
 * live history.
 */
class MessageDeleteController
{
    public function __invoke(DeleteMessageRequest $request, DeleteMessagePairAction $deletePair, HostedChatSession $session): void
    {
        $deletePair->execute(
            $session->scope(current_agent(), $request->conversationId()),
            $request->messageId(),
        );
    }
}

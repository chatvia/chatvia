<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\MessageFeedbackRequest;
use Domain\Conversation\Actions\Activity\RecordMessageFeedbackAction;

/**
 * Records the hosted-chat visitor's rating of an assistant reply.
 */
class MessageFeedbackController
{
    public function __invoke(MessageFeedbackRequest $request, RecordMessageFeedbackAction $recordFeedback, HostedChatSession $session): void
    {
        $recordFeedback->execute(
            $session->scope(current_agent(), $request->conversationId()),
            $request->messageId(),
            $request->feedback(),
        );
    }
}

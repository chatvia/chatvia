<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\MessageFeedbackRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\RecordMessageFeedbackAction;

/**
 * Records the hosted-chat visitor's rating of an assistant reply.
 */
class MessageFeedbackController
{
    public function __invoke(
        MessageFeedbackRequest $request,
        #[HostedChatAgent] Agent $agent,
        RecordMessageFeedbackAction $recordFeedback,
        HostedChatSession $session,
    ): void {
        $recordFeedback->execute(
            $session->scope($agent, $request->conversationId()),
            $request->messageId(),
            $request->feedback(),
        );
    }
}

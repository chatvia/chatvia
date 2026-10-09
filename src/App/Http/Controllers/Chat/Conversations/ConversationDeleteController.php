<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\DeleteConversationAction;
use Domain\Conversation\Models\Conversation;

/**
 * Erases one of the hosted-chat visitor's conversations.
 */
class ConversationDeleteController
{
    public function __invoke(
        string $conversationId,
        #[HostedChatAgent] Agent $agent,
        DeleteConversationAction $deleteConversation,
        HostedChatSession $session,
    ): void {
        $scope = $session->scope($agent, $conversationId);

        // The conversation must be in the CALLER's session of this agent, so
        // nobody can delete another visitor's conversation by its id —
        // including on public agents.
        Conversation::query()
            ->inSession($scope->sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->firstOrFail();

        $deleteConversation->execute($scope);
    }
}

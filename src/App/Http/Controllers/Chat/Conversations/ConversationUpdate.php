<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\RenameConversationRequest;
use Domain\Conversation\Actions\Conversations\RenameConversationAction;
use Domain\Conversation\Models\Conversation;

/**
 * Renames one of the hosted-chat visitor's conversations.
 */
class ConversationUpdate
{
    public function __invoke(RenameConversationRequest $request, string $conversationId, RenameConversationAction $renameConversation, HostedChatSession $session): void
    {
        $agent = current_agent();
        $scope = $session->scope($agent, $conversationId);

        // Only a conversation in the CALLER's session of this agent.
        Conversation::query()
            ->inSession($scope->sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->firstOrFail();

        $renameConversation->execute($scope, $request->title());
    }
}

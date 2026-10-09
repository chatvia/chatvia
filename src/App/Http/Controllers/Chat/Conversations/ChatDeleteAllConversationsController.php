<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\DeleteSessionConversationsAction;

/**
 * Erases every conversation of the hosted-chat visitor's session.
 */
class ChatDeleteAllConversationsController
{
    public function __invoke(
        #[HostedChatAgent] Agent $agent,
        DeleteSessionConversationsAction $deleteConversations,
        HostedChatSession $session,
    ): void {
        $deleteConversations->execute($session->scope($agent));
    }
}

<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatSession;
use Domain\Conversation\Actions\Removal\DeleteSessionConversationsAction;

/**
 * Erases every conversation of the hosted-chat visitor's session.
 */
class ChatDeleteAllConversationsController
{
    public function __invoke(DeleteSessionConversationsAction $deleteConversations, HostedChatSession $session): void
    {
        $deleteConversations->execute($session->scope(current_agent()));
    }
}

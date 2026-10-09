<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatSession;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * The hosted chat's sidebar: the visitor's visible conversations, newest first.
 */
class ConversationController
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(ChatMemory $memory, HostedChatSession $session): array
    {
        return $memory
            ->withScope($session->scope(current_agent()))
            ->visibleConversationsNewestFirst();
    }
}

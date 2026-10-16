<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * The hosted chat's sidebar: the visitor's visible conversations, newest first.
 */
class ConversationController
{
    /**
     * @return list<ConversationData>
     */
    public function __invoke(#[HostedChatAgent] Agent $agent, ChatMemory $memory, HostedChatSession $session): array
    {
        return $memory
            ->withScope($session->scope($agent))
            ->visibleConversationsNewestFirst();
    }
}

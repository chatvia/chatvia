<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatSession;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * The knowledge sources the answers of one hosted-chat conversation were
 * based on, keyed by assistant message id.
 */
class ChatSourceController
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function __invoke(string $conversationId, ChatMemory $memory, HostedChatSession $session): array
    {
        return $memory
            ->withScope($session->scope(current_agent(), $conversationId))
            ->getSources();
    }
}

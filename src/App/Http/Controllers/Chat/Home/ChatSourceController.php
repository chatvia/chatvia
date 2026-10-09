<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
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
    public function __invoke(string $conversationId, #[HostedChatAgent] Agent $agent, ChatMemory $memory, HostedChatSession $session): array
    {
        return $memory
            ->withScope($session->scope($agent, $conversationId))
            ->getSources();
    }
}

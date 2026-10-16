<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\SourceRefData;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * The knowledge sources the answers of one hosted-chat conversation were
 * based on, keyed by assistant message id. None while the agent hides its
 * sources, including those remembered before the owner turned them off.
 */
class ChatSourceController
{
    /**
     * @return array<string, list<SourceRefData>>
     */
    public function __invoke(string $conversationId, #[HostedChatAgent] Agent $agent, ChatMemory $memory, HostedChatSession $session): array
    {
        if (! $agent->showsSources()) {
            return [];
        }

        return $memory
            ->withScope($session->scope($agent, $conversationId))
            ->getSources();
    }
}

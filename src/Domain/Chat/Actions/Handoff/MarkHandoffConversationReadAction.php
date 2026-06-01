<?php

namespace Domain\Chat\Actions\Handoff;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Services\Memory\ChatMemory;

class MarkHandoffConversationReadAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(Agent $agent, ChatConversation $conversation): int
    {
        $session = $conversation->session;

        if (! $session || $session->agent_id !== $agent->id) {
            return 0;
        }

        return $this->chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->external_id)
            ->setConversationId($conversation->external_id)
            ->markMessagesAsRead('admin');
    }
}

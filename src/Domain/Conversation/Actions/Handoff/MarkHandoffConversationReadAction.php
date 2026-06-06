<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;

class MarkHandoffConversationReadAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(Agent $agent, Conversation $conversation): int
    {
        $session = $conversation->session;

        if (! $session || $session->agent_id !== $agent->id) {
            return 0;
        }

        return $this->chatMemory
            ->withContext($agent, $session->external_id, $conversation->external_id)
            ->markMessagesAsRead('admin');
    }
}

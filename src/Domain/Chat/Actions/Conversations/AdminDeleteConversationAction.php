<?php

namespace Domain\Chat\Actions\Conversations;

use Domain\Agent\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Services\Memory\ChatMemory;

class AdminDeleteConversationAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(Agent $agent, ChatConversation $conversation): void
    {
        $session = $conversation->session;

        $this->chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->external_id)
            ->setConversationId($conversation->external_id)
            ->deleteConversation(deletedBy: 'admin');

        $conversation->delete();

        if ($session) {
            $remaining = $session->conversations()->count();

            if ($remaining === 0
                && $session->last_activity_at
                && $session->last_activity_at->lt(now()->subDays(30))
            ) {
                $session->delete();
            }
        }
    }
}

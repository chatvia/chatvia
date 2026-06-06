<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;

class AdminDeleteConversationAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(Agent $agent, Conversation $conversation): void
    {
        $session = $conversation->session;

        $this->chatMemory
            ->withContext($agent, $session->external_id, $conversation->external_id)
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

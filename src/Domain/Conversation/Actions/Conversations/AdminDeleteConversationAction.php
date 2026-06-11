<?php

namespace Domain\Conversation\Actions\Conversations;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;

class AdminDeleteConversationAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(Agent $agent, Conversation $conversation, ?User $actor = null): void
    {
        $session = $conversation->session;

        // Record WHO deleted, not just that an admin did. `deleted_by` is
        // varchar(10) — too narrow for an email — so stamp the acting user's
        // id; 'admin' stays the fallback for callers without an actor.
        $this->chatMemory
            ->withContext($agent, $session->external_id, $conversation->external_id)
            ->deleteConversation(deletedBy: $actor !== null ? (string) $actor->id : 'admin');

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

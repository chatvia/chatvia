<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class ConversationDeleteController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, TurnState $session): void
    {
        $agent = current_agent();
        $callerSession = $session->firstOrCreateSession($agent);

        // Scope the conversation to the CALLER's session + agent (not the
        // target row's own session) so a user can't delete another user's
        // conversation by external id — including on public agents.
        Conversation::query()
            ->where('external_id', $conversationId)
            ->whereHas('session', function ($q) use ($callerSession, $agent): void {
                $q->where('external_id', $callerSession)->where('agent_id', $agent->id);
            })
            ->firstOrFail();

        $memory
            ->withContext($agent, $callerSession, $conversationId)
            ->deleteConversation();
    }
}

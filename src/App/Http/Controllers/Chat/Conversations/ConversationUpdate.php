<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class ConversationUpdate
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, TurnState $session): void
    {
        $agent = current_agent();
        $callerSession = $session->firstOrCreateSession($agent);

        // Scope the conversation to the CALLER's session + agent so a user
        // can't rename another user's conversation by external id.
        Conversation::query()
            ->where('external_id', $conversationId)
            ->whereHas('session', function ($q) use ($callerSession, $agent): void {
                $q->where('external_id', $callerSession)->where('agent_id', $agent->id);
            })
            ->firstOrFail();

        $memory
            ->withContext($agent, $callerSession, $conversationId)
            ->updateConversationTitle($request->input('title'));
    }
}

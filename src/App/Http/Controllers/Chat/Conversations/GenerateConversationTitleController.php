<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\JsonResponse;

/**
 * Name a conversation from its messages, synchronously. Titling is a quick
 * single LLM call, so it runs in-request (no queue) and the client fires it
 * async after the first turn, then refreshes the sidebar. Idempotent: a no-op
 * once the conversation already has a real title.
 */
class GenerateConversationTitleController
{
    public function __invoke(string $conversationId, ChatMemory $memory, TurnState $session): JsonResponse
    {
        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateSession($agent))
            ->setConversationId($conversationId);

        abort_if(! $memory->conversationExists(), 404);

        $conversation = Conversation::where('external_id', $conversationId)->first();

        if ($conversation && $conversation->needsTitle()) {
            $memory->updateConversationTitleFromMessages();
            $conversation->refresh();
        }

        return response()->json(['title' => $conversation?->title]);
    }
}

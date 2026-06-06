<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Domain\Llm\Usage\TokenUsageContext;
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
            ->withContext($agent, $session->firstOrCreateSession($agent), $conversationId);

        abort_if(! $memory->conversationExists(), 404);

        $conversation = Conversation::where('external_id', $conversationId)->first();

        if ($conversation && $conversation->needsTitle()) {
            app(TokenUsageContext::class)->track(
                $agent->user_id,
                fn () => $memory->updateConversationTitleFromMessages(),
                'chat',
            );
            $conversation->refresh();
        }

        return response()->json(['title' => $conversation?->title]);
    }
}

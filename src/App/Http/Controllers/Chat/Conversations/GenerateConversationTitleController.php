<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatSession;
use Domain\Conversation\Actions\Conversations\GenerateConversationTitleAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
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
    public function __invoke(
        string $conversationId,
        ChatMemory $memory,
        HostedChatSession $session,
        GenerateConversationTitleAction $generateTitle,
        TokenUsageContext $usage,
    ): JsonResponse {
        $agent = current_agent();
        $scope = $session->scope($agent, $conversationId);

        abort_if(! $memory->withScope($scope)->conversationExists(), 404);

        $conversation = Conversation::query()
            ->inSession($scope->sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation?->needsTitle()) {
            // The title is an LLM call: attribute its tokens to the workspace.
            $usage->track($agent->user_id, fn () => $generateTitle->execute($scope), 'chat');
            $conversation->refresh();
        }

        return response()->json(['title' => $conversation?->title]);
    }
}

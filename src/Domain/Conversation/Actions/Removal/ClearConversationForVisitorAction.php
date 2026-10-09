<?php

namespace Domain\Conversation\Actions\Removal;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use InvalidArgumentException;

/**
 * Clears one conversation from the visitor's widget without erasing it. The
 * row and its messages stay as they are, so the workspace keeps the full chat
 * log and analytics; only the visitor stops seeing it. `cleared_at` keeps a
 * cold-cache rebuild of the visitor's list from bringing it back.
 *
 * Erasure is {@see DeleteConversationAction}, reserved for the owner, admins
 * and the API.
 */
class ClearConversationForVisitorAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope): void
    {
        $conversationId = $scope->conversationId
            ?? throw new InvalidArgumentException('A conversation id is required to clear a conversation.');

        $conversation = Conversation::query()
            ->inSession($scope->sessionId, $scope->agent->id)
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation === null) {
            return;
        }

        $conversation->update([
            'cleared_at' => now(),
            'deleted_by' => Conversation::DELETED_BY_VISITOR,
        ]);

        // Drop the live history so the visitor truly starts fresh; the
        // database row is the workspace's copy.
        $memory = $this->memory->withScope($scope);
        $memory->flushConversation($conversationId);
        $memory->forgetConversation($conversationId);
    }
}

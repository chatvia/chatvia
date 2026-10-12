<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use InvalidArgumentException;

/**
 * Renames a conversation and makes it visible in the visitor's list — in the
 * live memory and in the database, so a later rebuild of the list from the
 * database does not reset the sidebar to "New Chat". A conversation that was
 * hidden also gets the full retention window.
 */
class RenameConversationAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope, string $title): void
    {
        $conversationId = $scope->conversationId
            ?? throw new InvalidArgumentException('A conversation id is required to rename a conversation.');

        // Generated titles often come back wrapped in quotes.
        $cleanTitle = str_replace('"', '', $title);

        $this->memory->withScope($scope)->renameConversation($conversationId, $cleanTitle);

        // Update the loaded model, not the query builder: only the model
        // applies the encrypted cast on `title`.
        $conversation = Conversation::query()
            ->inSession($scope->sessionId, $scope->agent->id)
            ->where('external_id', $conversationId)
            ->first();

        // A hidden conversation that becomes visible also gets the full
        // retention window; a visible one keeps its horizon.
        $conversation?->update([
            'title' => $cleanTitle,
            ...($conversation->is_visible ? [] : Conversation::visibleAttributes()),
        ]);
    }
}

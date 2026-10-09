<?php

namespace Domain\Conversation\Actions\Removal;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Clears every conversation of a visitor session from the widget, with the
 * same view-reset meaning as {@see ClearConversationForVisitorAction}: the
 * content and files stay for the workspace, nothing is soft-deleted.
 */
class ClearSessionConversationsForVisitorAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope): void
    {
        $memory = $this->memory->withScope($scope);

        $sessionId = Session::query()
            ->where('agent_id', $scope->agent->id)
            ->where('external_id', $scope->sessionId)
            ->value('id');

        if ($sessionId !== null) {
            Conversation::query()
                ->where('chat_session_id', $sessionId)
                ->whereNull('cleared_at')
                ->update([
                    'cleared_at' => now(),
                    'deleted_by' => Conversation::DELETED_BY_VISITOR,
                    'updated_at' => now(),
                ]);
        }

        $memory->flushSession();
    }
}

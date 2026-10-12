<?php

namespace Domain\Conversation\Actions\Removal;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Erases one conversation: wipes the text of its messages (and their search
 * copy), records who deleted it, and soft-deletes it — which also deletes its
 * uploaded files. The conversation then leaves the live memory and the
 * visitor's list. A conversation that is already gone (a repeated request, or
 * the prune got there first) is only forgotten by the memory.
 *
 * This is erasure. A visitor clearing their own widget uses
 * {@see ClearConversationForVisitorAction} instead, which keeps the content
 * for the workspace's chat log.
 */
class DeleteConversationAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope, ?string $deletedBy = null): void
    {
        $conversationId = $scope->conversationId
            ?? throw new InvalidArgumentException('A conversation id is required to delete a conversation.');

        DB::transaction(function () use ($scope, $conversationId, $deletedBy): void {
            $conversation = Conversation::query()
                ->inSession($scope->sessionId, $scope->agent->id)
                ->where('external_id', $conversationId)
                ->first();

            if ($conversation === null) {
                return;
            }

            // Erase the text now: the row itself goes with the trash purge.
            $conversation->messages()->update([
                'content' => null,
            ]);

            if ($deletedBy) {
                $conversation->update(['deleted_by' => $deletedBy]);
            }

            $conversation->delete();
        });

        $memory = $this->memory->withScope($scope);
        $memory->flushConversation($conversationId);
        $memory->forgetConversation($conversationId);
    }
}

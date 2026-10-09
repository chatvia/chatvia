<?php

namespace Domain\Conversation\Actions\Removal;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;

/**
 * Erases every conversation of one visitor session (the hosted chat's
 * "delete all"): deletes their uploaded files, wipes the message text and its
 * search copy, soft-deletes the conversations, and empties the session's live
 * memory.
 */
class DeleteSessionConversationsAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope, ?string $deletedBy = null): void
    {
        $memory = $this->memory->withScope($scope);

        $sessionId = Session::query()
            ->where('agent_id', $scope->agent->id)
            ->where('external_id', $scope->sessionId)
            ->value('id');

        if ($sessionId !== null) {
            DB::transaction(fn () => $this->erase($sessionId, $deletedBy));
        }

        $memory->flushSession();
    }

    /**
     * The bulk updates below skip model events, so the per-row cascades are
     * run here by hand: files are deleted one by one (each delete removes the
     * stored object), and the BM25 copy is cleared next to the text.
     */
    private function erase(int $sessionId, ?string $deletedBy): void
    {
        $conversationIds = Conversation::query()
            ->where('chat_session_id', $sessionId)
            ->pluck('id');

        Attachment::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->get()
            ->each(static fn (Attachment $attachment) => $attachment->delete());

        Message::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->update(['content' => null, 'content_bm25' => null]);

        Conversation::query()
            ->whereIn('id', $conversationIds)
            ->update([
                'deleted_by' => $deletedBy,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }
}

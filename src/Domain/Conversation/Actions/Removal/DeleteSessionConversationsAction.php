<?php

namespace Domain\Conversation\Actions\Removal;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;
use Illuminate\Support\Facades\DB;

/**
 * Erases every conversation of one visitor session (the hosted chat's
 * "delete all"): deletes their uploaded files and the replies read aloud,
 * wipes the message text, soft-deletes the conversations, and empties the
 * session's live memory.
 */
class DeleteSessionConversationsAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly MessageSpeechFiles $speechFiles,
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
     * run here by hand: files are deleted one by one, as each delete removes
     * the stored object.
     */
    private function erase(int $sessionId, ?string $deletedBy): void
    {
        $conversations = Conversation::query()
            ->where('chat_session_id', $sessionId)
            ->get(['id', 'external_id', 'agent_id']);
        $conversationIds = $conversations->pluck('id');

        $this->speechFiles->forgetConversations($conversations);

        Attachment::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->get()
            ->each(static fn (Attachment $attachment) => $attachment->delete());

        Message::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->update(['content' => null]);

        Conversation::query()
            ->whereIn('id', $conversationIds)
            ->update([
                'deleted_by' => $deletedBy,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }
}

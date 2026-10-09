<?php

namespace Domain\Conversation\Actions\Removal;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\DB;

/**
 * Erases a conversation on the request of the owner, a teammate or an API
 * client: wipes the message text and soft-deletes the conversation (through
 * {@see DeleteConversationAction}, so the visitor's live copy goes too), and
 * removes the parent session when nothing is left in it and it has been idle
 * for a while.
 */
class AdminDeleteConversationAction
{
    /**
     * An empty session younger than this is kept: the visitor may still be
     * around and start a new conversation in it.
     */
    private const int EMPTY_SESSION_IDLE_DAYS = 30;

    public function __construct(
        private readonly DeleteConversationAction $deleteConversation,
    ) {}

    public function execute(Agent $agent, Conversation $conversation, ?User $actor = null): void
    {
        $session = $conversation->session;

        // chat_session_id is a required foreign key with ON DELETE CASCADE, so a
        // missing session means the database already removed this conversation
        // and its messages along with it. Nothing is left to erase.
        if ($session === null) {
            return;
        }

        // Record WHO deleted it. `deleted_by` is varchar(10) — too narrow for
        // an email — so the acting user's id is stored.
        $deletedBy = $actor !== null ? (string) $actor->id : Conversation::DELETED_BY_ADMIN;

        DB::transaction(function () use ($agent, $conversation, $session, $deletedBy): void {
            $this->deleteConversation->execute(
                new ChatScopeData($agent, $session->external_id, $conversation->external_id),
                $deletedBy,
            );

            if ($session->conversations()->doesntExist()
                && $session->last_activity_at
                && $session->last_activity_at->lt(now()->subDays(self::EMPTY_SESSION_IDLE_DAYS))
            ) {
                $session->delete();
            }
        });
    }
}

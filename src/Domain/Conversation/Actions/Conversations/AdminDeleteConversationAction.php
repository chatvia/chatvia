<?php

namespace Domain\Conversation\Actions\Conversations;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;

/**
 * Erases a conversation on the owner's (or a teammate's / API client's)
 * request: wipes the message text, soft-deletes the conversation through
 * ChatMemory (so the visitor's cached copy goes too), and removes the parent
 * session when nothing is left in it and it has been idle for a while.
 */
class AdminDeleteConversationAction
{
    /**
     * An empty session younger than this is kept: the visitor may still be
     * around and start a new conversation in it.
     */
    private const EMPTY_SESSION_IDLE_DAYS = 30;

    public function __construct(
        private readonly ChatMemory $chatMemory,
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

        // Record WHO deleted, not just that an admin did. `deleted_by` is
        // varchar(10) — too narrow for an email — so stamp the acting user's
        // id; 'admin' stays the fallback for callers without an actor.
        $deletedBy = $actor !== null ? (string) $actor->id : 'admin';

        DB::transaction(function () use ($agent, $conversation, $session, $deletedBy): void {
            // Erases the message text and soft-deletes the conversation (one
            // delete — calling delete() again here would re-run the cascade).
            $this->chatMemory
                ->withContext($agent, $session->external_id, $conversation->external_id)
                ->deleteConversation(deletedBy: $deletedBy);

            if ($session->conversations()->doesntExist()
                && $session->last_activity_at
                && $session->last_activity_at->lt(now()->subDays(self::EMPTY_SESSION_IDLE_DAYS))
            ) {
                $session->delete();
            }
        });
    }
}

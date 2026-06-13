<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Permanently delete a chat member. Cascading FKs handle every artefact tied
 * to them — `agent_user` grants, `user_socials` identities, any open
 * `agent_chatter_invitations` row, plus their chat sessions on the cascade chain.
 * Workspace owners / employees / admins are explicitly out of scope and
 * should be rejected before this Action is called.
 *
 * Two artefacts the FK cascade can't reach are cleaned explicitly, because the
 * cascade drops rows WITHOUT firing Eloquent events: attachment files on S3
 * (the Attachment `deleting` observer) and the chatter's Redis chat memory
 * (cache, not DB — and the `forever` ownership markers never expire on their
 * own). Both are scoped to this chatter's own sessions, so siblings on the
 * same workspace keep their conversations. (The workspace-wide teardown does
 * the same via a single workspace-tag flush — see {@see DeleteUserAccountAction};
 * per chatter the volume is tiny, so we enumerate instead of adding a
 * per-chatter cache tag and its per-request hot-path cost.)
 */
class DeleteChatterAction
{
    public function execute(User $chatter): void
    {
        // Eager-load now so the external ids survive the cascade for the
        // post-delete cache flush; withTrashed so a soft-deleted conversation's
        // ownership marker is swept too.
        $sessions = Session::query()
            ->where('user_id', $chatter->id)
            ->with(['conversations' => fn ($query) => $query->withTrashed()])
            ->get();

        // Wipe attachment files through Eloquent BEFORE the cascade drops the
        // rows silently — chat_attachments cascades off both the conversation
        // and the message, neither of which fires the S3-wiping observer.
        $this->wipeAttachmentFiles($sessions);

        $chatter->delete();

        $this->flushChatMemory($sessions);
    }

    /**
     * @param  Collection<int, Session>  $sessions
     */
    private function wipeAttachmentFiles(Collection $sessions): void
    {
        $conversationIds = $sessions
            ->flatMap(fn (Session $session): iterable => $session->conversations)
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return;
        }

        Attachment::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->get()
            ->each(static fn (Attachment $attachment) => $attachment->delete());
    }

    /**
     * Per-session + per-conversation tag flushes cover every chat-memory entry
     * the deleted chatter owned: the conversation-list/session-data entry under
     * the session tag, message + source caches under both, and the `forever`
     * ownership markers under the conversation tag. Best-effort — removing a
     * member must not fail because the cache store is briefly unreachable, so
     * log and carry on (the rows are already gone).
     *
     * @param  Collection<int, Session>  $sessions
     */
    private function flushChatMemory(Collection $sessions): void
    {
        try {
            foreach ($sessions as $session) {
                foreach ($session->conversations as $conversation) {
                    Cache::driver('chat')->tags(ChatCache::conversationTag($conversation->external_id))->flush();
                }

                Cache::driver('chat')->tags(ChatCache::sessionTag($session->external_id))->flush();
            }
        } catch (Throwable $e) {
            Log::warning('Chatter deletion: failed to flush chat memory cache.', [
                'sessions' => $sessions->count(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

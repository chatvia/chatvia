<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Permanently delete a chatter. Cascading FKs handle every artefact tied to
 * them — `agent_chatter` grants, their message limit, reset tokens, plus their
 * chat sessions on the cascade chain. Their open invitations are keyed by
 * email, not by the chatter, so they are deleted here. The captured leads,
 * the invitations and the chatter row go in one transaction; files and cache
 * are cleaned around it.
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
    public function execute(Chatter $chatter): void
    {
        // Eager-load now so the external ids survive the cascade for the
        // post-delete cache flush; withTrashed so a soft-deleted conversation's
        // ownership marker is swept too.
        $sessions = Session::query()
            ->where('chatter_id', $chatter->id)
            ->with(['conversations' => fn ($query) => $query->withTrashed()])
            ->get();

        $conversationIds = $sessions
            ->flatMap(fn (Session $session): iterable => $session->conversations)
            ->pluck('id');

        // Clean up the artefacts the FK cascade can't reach correctly BEFORE
        // $chatter->delete() drops the rows: attachment files on S3 (the cascade
        // never fires the S3-wiping observer) and captured leads (chat_conversation_id
        // is SET NULL, so a lead would otherwise outlive the chatter and keep its PII).
        // Capture the external id before the row is gone — the chatter's avatar
        // lives on the assets disk at chatters/{external_id}/profile-photos/….
        $chatterExternalId = $chatter->external_id;

        $this->wipeAttachmentFiles($conversationIds);

        DB::transaction(function () use ($chatter, $conversationIds): void {
            $this->deleteLeads($conversationIds);
            $this->deleteInvitations($chatter);

            $chatter->delete();
        });

        $this->wipeAvatar($chatterExternalId);

        $this->flushChatMemory($sessions);
    }

    /**
     * Wipe the chatter's avatar tree on the assets disk (CDN). Best-effort: the
     * chatter row is already gone, so a briefly-unreachable CDN must not fail the
     * deletion — mirrors {@see DeleteUserAccountAction::wipeWorkspaceStorage()}.
     */
    private function wipeAvatar(string $externalId): void
    {
        try {
            Storage::disk('assets')->deleteDirectory('chatters/'.$externalId);
        } catch (Throwable $e) {
            Log::warning('Chatter deletion: failed to wipe avatar tree on the assets disk.', [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  SupportCollection<int, int>  $conversationIds
     */
    private function wipeAttachmentFiles(SupportCollection $conversationIds): void
    {
        if ($conversationIds->isEmpty()) {
            return;
        }

        Attachment::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->get()
            ->each(static fn (Attachment $attachment) => $attachment->delete());
    }

    /**
     * Delete leads captured from this chatter's conversations. `form_submissions.chat_conversation_id`
     * is SET NULL, so without this a lead — which holds the chatter's own PII
     * (name / email / phone, encrypted) — would outlive the chatter it came from.
     * Bulk delete is safe: Lead has only a `saving` hook, no `deleting` side effect.
     *
     * @param  SupportCollection<int, int>  $conversationIds
     */
    private function deleteLeads(SupportCollection $conversationIds): void
    {
        if ($conversationIds->isEmpty()) {
            return;
        }

        FormSubmission::query()->whereIn('chat_conversation_id', $conversationIds)->delete();
    }

    /**
     * Open invitations of this chatter's email to the workspace's agents. They
     * hold the email (PII) and would otherwise outlive the chatter.
     */
    private function deleteInvitations(Chatter $chatter): void
    {
        if ($chatter->email === null) {
            return;
        }

        AgentChatterInvitation::query()
            ->whereIn('agent_id', Agent::query()->where('workspace_id', $chatter->workspace_id)->select('id'))
            ->whereRaw('lower(email) = ?', [strtolower($chatter->email)])
            ->delete();
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

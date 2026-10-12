<?php

namespace Domain\Conversation\Actions\Retention;

use Domain\Conversation\Data\Retention\ConversationPruneResultData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ConversationRetention;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The daily conversation retention sweep. First pulls every horizon back to
 * the install's current retention, so a lowered setting also applies to old
 * rows. Then soft-deletes conversations past their retention — a workspace's
 * shorter override, or the horizon stamped on `expires_at` — hard-deletes
 * those soft-deleted longer than the grace window, so the chat tables really
 * shrink, and removes anonymous sessions left empty.
 *
 * The soft deletes go one by one through the model, never in bulk, so the
 * delete hook also removes their uploaded files and their live memory. Every
 * other step works in batches of `$chunk` rows, so one run never holds a huge
 * statement.
 */
class PruneExpiredConversationsAction
{
    /** An anonymous session that never had a conversation is removed after this long. */
    public const int ORPHAN_SESSION_DAYS = 90;

    /**
     * How far back past {@see ORPHAN_SESSION_DAYS} the never-used sessions are
     * looked for. The sweep runs daily, so a few days of slack is enough, and
     * the run reads only that slice of the sessions.
     */
    public const int UNUSED_SESSION_LOOKBACK_DAYS = 7;

    public function execute(int $chunk, int $graceDays): ConversationPruneResultData
    {
        $clamped = $this->clampToRetention($chunk);
        $overridden = $this->pruneWorkspaceOverrides($chunk);
        $expired = $this->deleteEach(
            Conversation::query()->whereNotNull('expires_at')->where('expires_at', '<', now()),
            $chunk,
        );
        $purgedSessionIds = $this->purgeTrashed($chunk, $graceDays);

        return new ConversationPruneResultData(
            clamped: $clamped,
            overridden: $overridden,
            expired: $expired,
            purged: $purgedSessionIds->count(),
            orphanSessions: $this->deleteEmptiedSessions($purgedSessionIds, $chunk) + $this->deleteUnusedSessions($chunk),
        );
    }

    /**
     * Pulls every horizon later than the install's current retention allows
     * (`now + N days`) back to `updated_at + N days`. The setting can be
     * lowered at any time, and the horizon stamped earlier would otherwise
     * keep a conversation longer than the install now promises. Served by
     * conversations_expires_cleanup_idx.
     */
    private function clampToRetention(int $chunk): int
    {
        $days = ConversationRetention::days();
        // Whole seconds, like the column, so a clamped row never matches again.
        $now = now()->startOfSecond();
        $clamped = 0;

        do {
            $batch = DB::update(
                'UPDATE chat_conversations
                 SET expires_at = LEAST(updated_at, ?::timestamp) + make_interval(days => ?::int)
                 WHERE id IN (
                     SELECT id FROM chat_conversations
                     WHERE expires_at > ?::timestamp
                       AND deleted_at IS NULL
                     LIMIT ?
                 )',
                [$now, $days, $now->copy()->addDays($days), $chunk],
            );

            $clamped += $batch;
        } while ($batch > 0);

        return $clamped;
    }

    /**
     * Workspaces that shortened conversation retention below the install's
     * (WorkspaceSecurityPolicy.retention_conversations_days): their
     * conversations idle past the override are deleted, anchored on
     * updated_at like the install's sliding window. The window is clamped to
     * the configured floor and to the install's CURRENT retention, so an
     * override saved before the admin lowered it never keeps data longer.
     */
    private function pruneWorkspaceOverrides(int $chunk): int
    {
        $floor = (int) config('chatvia.retention.conversations_floor_days', 1);
        $ceiling = ConversationRetention::days();

        $overrides = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_conversations_days')
            ->pluck('retention_conversations_days', 'workspace_id');

        $deleted = 0;

        foreach ($overrides as $workspaceId => $days) {
            $retentionDays = min(max($floor, (int) $days), $ceiling);

            $deleted += $this->deleteEach(
                Conversation::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('updated_at', '<', now()->subDays($retentionDays)),
                $chunk,
            );
        }

        return $deleted;
    }

    /**
     * Soft-delete every live conversation the query matches, through the
     * model so the delete hook runs.
     *
     * @param  Builder<Conversation>  $query
     */
    private function deleteEach(Builder $query, int $chunk): int
    {
        $deleted = 0;

        $query->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById($chunk, function ($conversations) use (&$deleted): void {
                foreach ($conversations as $conversation) {
                    $conversation->delete();
                    $deleted++;
                }
            });

        return $deleted;
    }

    /**
     * Hard-delete conversations soft-deleted before the grace window, a batch
     * at a time (found through conversations_trashed_idx). Their messages go
     * with them through the foreign-key cascade. The soft delete already
     * removed their files and live memory; a file still attached (a bulk soft
     * delete that skipped the hook) is removed through its model first, so no
     * stored file outlives its row.
     *
     * @return Collection<int, int> the session id of each purged conversation
     */
    private function purgeTrashed(int $chunk, int $graceDays): Collection
    {
        $cutoff = now()->subDays($graceDays);
        $sessionIds = collect();

        do {
            $batch = DB::table('chat_conversations')
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', $cutoff)
                ->limit($chunk)
                ->get(['id', 'chat_session_id']);

            if ($batch->isEmpty()) {
                break;
            }

            $ids = $batch->pluck('id');

            Attachment::query()->whereIn('chat_conversation_id', $ids)->get()
                ->each(static fn (Attachment $attachment) => $attachment->delete());

            $purged = DB::table('chat_conversations')->whereIn('id', $ids)->delete();

            $sessionIds = $sessionIds->merge($batch->pluck('chat_session_id')->map(static fn ($id): int => (int) $id));
        } while ($purged > 0);

        return $sessionIds;
    }

    /**
     * The anonymous sessions the purge left with no conversation. Only the
     * purged conversations' sessions are checked, so the sweep never scans the
     * whole sessions table. A chatter's or staff member's session is kept.
     *
     * @param  Collection<int, int>  $sessionIds
     */
    private function deleteEmptiedSessions(Collection $sessionIds, int $chunk): int
    {
        $deleted = 0;

        foreach ($sessionIds->unique()->values()->chunk($chunk) as $ids) {
            $deleted += $this->anonymousSessionsWithoutConversations()
                ->whereIn('id', $ids->values())
                ->delete();
        }

        return $deleted;
    }

    /**
     * Anonymous sessions that never had a conversation (a hosted-chat visit
     * that sent nothing), once they are {@see ORPHAN_SESSION_DAYS} old. Only
     * the sessions that reached that age in the last
     * {@see UNUSED_SESSION_LOOKBACK_DAYS} are read.
     */
    private function deleteUnusedSessions(int $chunk): int
    {
        $olderThan = now()->subDays(self::ORPHAN_SESSION_DAYS);
        $newerThan = $olderThan->copy()->subDays(self::UNUSED_SESSION_LOOKBACK_DAYS);
        $deleted = 0;

        do {
            $batch = DB::table('chat_sessions')
                ->whereIn('id', $this->anonymousSessionsWithoutConversations()
                    ->select('id')
                    ->whereBetween('created_at', [$newerThan, $olderThan])
                    ->limit($chunk))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }

    /** Sessions of no chatter or staff member that have no conversation, not even a soft-deleted one. */
    private function anonymousSessionsWithoutConversations(): QueryBuilder
    {
        return DB::table('chat_sessions')
            ->whereNull('user_id')
            ->whereNull('chatter_id')
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->select(DB::raw(1))
                ->from('chat_conversations')
                ->whereColumn('chat_conversations.chat_session_id', 'chat_sessions.id'));
    }
}

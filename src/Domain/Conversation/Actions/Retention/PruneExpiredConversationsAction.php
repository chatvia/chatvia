<?php

namespace Domain\Conversation\Actions\Retention;

use Closure;
use Domain\Conversation\Data\Retention\ConversationPruneResultData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ConversationRetention;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Support\License\Entitlements;

/**
 * The daily conversation retention sweep. First pulls every horizon back to
 * the install's current retention, so a lowered setting also applies to old
 * rows. Then soft-deletes conversations past their retention — a workspace's
 * shorter override, or the horizon stamped on `expires_at` — hard-deletes
 * those soft-deleted longer than the grace window, so the chat tables really
 * shrink, and removes anonymous sessions left empty.
 *
 * The soft deletes do, a batch at a time, what the model's delete hook does
 * for one row: they also remove the uploaded files and the live memory.
 * Every step works in batches of `$chunk` rows, so one run never holds a huge
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

    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    public function execute(int $chunk, int $graceDays): ConversationPruneResultData
    {
        $clamped = $this->clampToRetention($chunk);
        $overridden = $this->pruneWorkspaceOverrides($chunk);
        // Served by conversations_expires_cleanup_idx.
        $expired = $this->softDeleteInBatches(
            fn (): Builder => Conversation::query()->whereNotNull('expires_at')->where('expires_at', '<', now()),
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
     *
     * Read agent by agent, so conversations_agent_visible_idx (agent_id,
     * updated_at, id) serves each read. It covers visible conversations
     * only, which is enough: a hidden one always carries an `expires_at` at
     * most {@see Conversation::HIDDEN_LIFETIME_DAYS} after it started, which
     * the floor (at least one day) never undercuts, so the expiry pass of
     * this same run removes it.
     */
    private function pruneWorkspaceOverrides(int $chunk): int
    {
        // Custom retention is an Enterprise feature; the install's own window still applies.
        if (! $this->entitlements->enterprise()) {
            return 0;
        }

        $floor = (int) config('chatvia.retention.conversations_floor_days', 1);
        $ceiling = ConversationRetention::days();

        $overrides = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_conversations_days')
            ->pluck('retention_conversations_days', 'workspace_id');

        $deleted = 0;

        foreach ($overrides as $workspaceId => $days) {
            $idleSince = now()->subDays(min(max($floor, (int) $days), $ceiling));

            // A base query, so the agents in the trash still count: their
            // conversations stay until the agent is purged.
            $agentIds = DB::table('agents')->where('workspace_id', $workspaceId)->pluck('id');

            foreach ($agentIds as $agentId) {
                $deleted += $this->softDeleteInBatches(
                    fn (): Builder => Conversation::query()
                        ->where('agent_id', $agentId)
                        ->where('is_visible', true)
                        ->where('updated_at', '<', $idleSince),
                    $chunk,
                );
            }
        }

        return $deleted;
    }

    /**
     * Soft-delete every live conversation the query matches, a batch at a
     * time. A batch is "the next N" with no ORDER BY: a soft-deleted row
     * leaves the partial indexes these queries use (`deleted_at IS NULL`), so
     * each read finds rows not seen yet and the loop ends when none are left.
     *
     * Each batch does what the model's delete hook does for one row: the
     * files go through their model (which removes the stored file), one
     * UPDATE moves the rows to the trash (kept soft, so the chat log writer
     * can bring an expired empty conversation back), and one flush clears
     * their live memory.
     *
     * @param  Closure(): Builder<Conversation>  $matching
     */
    private function softDeleteInBatches(Closure $matching, int $chunk): int
    {
        $deleted = 0;

        do {
            $batch = $matching()->limit($chunk)->toBase()->get(['id', 'external_id']);

            if ($batch->isEmpty()) {
                break;
            }

            $ids = $batch->pluck('id')->map(static fn ($id): int => (int) $id);

            $this->deleteAttachments($ids);

            $now = now();
            $trashed = DB::table('chat_conversations')
                ->whereRaw('id = ANY(?::bigint[])', ['{'.$ids->implode(',').'}'])
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            Cache::driver('chat')
                ->tags($batch->map(static fn (object $row): string => ChatCache::conversationTag($row->external_id))->all())
                ->flush();

            $deleted += $trashed;
        } while ($trashed > 0);

        return $deleted;
    }

    /**
     * Delete the files of these conversations through their model, so each
     * one's stored file goes with its row.
     *
     * @param  Collection<int, int>  $conversationIds
     */
    private function deleteAttachments(Collection $conversationIds): void
    {
        Attachment::query()->whereIn('chat_conversation_id', $conversationIds)->get()
            ->each(static fn (Attachment $attachment) => $attachment->delete());
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

            $this->deleteAttachments($ids);

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
            // A plain range on created_at, which chat_sessions_created_brin serves.
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

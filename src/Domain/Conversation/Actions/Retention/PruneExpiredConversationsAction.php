<?php

namespace Domain\Conversation\Actions\Retention;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Data\Retention\ConversationPruneResultData;
use Domain\Conversation\Models\Conversation;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The daily conversation retention sweep. Soft-deletes conversations past
 * their retention — a workspace's shorter override, or the plan horizon
 * stamped on `expires_at` — then hard-deletes those soft-deleted longer than
 * the grace window, so the chat tables really shrink, and removes anonymous
 * sessions left empty.
 *
 * Conversations are deleted one by one through the model, never in bulk, so
 * the delete hook also removes their uploaded files and their live memory.
 */
class PruneExpiredConversationsAction
{
    /** An anonymous session with no conversation left is removed after this long. */
    public const int ORPHAN_SESSION_DAYS = 90;

    public function execute(int $chunk, int $graceDays): ConversationPruneResultData
    {
        return new ConversationPruneResultData(
            overridden: $this->pruneWorkspaceOverrides($chunk),
            expired: $this->deleteEach(
                Conversation::query()->whereNotNull('expires_at')->where('expires_at', '<', now()),
                $chunk,
            ),
            purged: $this->purgeTrashed($chunk, $graceDays),
            orphanSessions: $this->deleteOrphanSessions(),
        );
    }

    /**
     * Workspaces that shortened conversation retention below their plan
     * (WorkspaceSecurityPolicy.retention_conversations_days): their
     * conversations idle past the override are deleted, anchored on
     * updated_at like the plan's sliding window. The window is clamped to the
     * configured floor and to the workspace's CURRENT plan ceiling, so a stale
     * override left behind by a downgrade never keeps data longer than the new
     * plan allows.
     */
    private function pruneWorkspaceOverrides(int $chunk): int
    {
        $floor = (int) config('chatvia.retention.conversations_floor_days', 1);

        $overrides = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_conversations_days')
            ->pluck('retention_conversations_days', 'workspace_id');

        if ($overrides->isEmpty()) {
            return 0;
        }

        $workspaces = User::query()->whereIn('id', $overrides->keys())->get()->keyBy('id');
        $deleted = 0;

        foreach ($overrides as $workspaceId => $days) {
            $workspace = $workspaces->get($workspaceId);

            if ($workspace === null) {
                continue;
            }

            $retentionDays = min(max($floor, (int) $days), (int) $workspace->capabilities->retentionDays);

            $deleted += $this->deleteEach(
                Conversation::query()
                    ->whereHas('agent', fn (Builder $query) => $query->where('user_id', $workspaceId))
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
     * Hard-delete conversations soft-deleted before the grace window; their
     * messages go with them through the foreign-key cascade.
     */
    private function purgeTrashed(int $chunk, int $graceDays): int
    {
        $purged = 0;

        Conversation::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($graceDays))
            ->orderBy('id')
            ->chunkById($chunk, function ($conversations) use (&$purged): void {
                foreach ($conversations as $conversation) {
                    $conversation->forceDelete();
                    $purged++;
                }
            });

        return $purged;
    }

    /** Anonymous sessions with no conversations left that have been idle a long time. */
    private function deleteOrphanSessions(): int
    {
        return DB::table('chat_sessions')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('chat_conversations')
                    ->whereColumn('chat_conversations.chat_session_id', 'chat_sessions.id');
            })
            ->whereNull('user_id')
            ->where('created_at', '<', now()->subDays(self::ORPHAN_SESSION_DAYS))
            ->delete();
    }
}

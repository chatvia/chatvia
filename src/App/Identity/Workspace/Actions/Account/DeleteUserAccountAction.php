<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Jobs\DeleteWorkspaceJob;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tear down a whole workspace, given its owner: every staff account (owner and
 * teammates), then the workspace row, whose FK cascade removes its teams,
 * chatters and every agent, knowledge source, message limit, etc. that hangs
 * off it.
 *
 * The unbounded tables go first, in small batches outside any transaction:
 * chunks, conversations (their messages and attachment rows go by cascade),
 * chat sessions, leads and the three audit logs (tool calls, guardrail
 * triggers, security events). The final transaction then only touches small
 * tables.
 *
 * What the FK cascade can't do, handled explicitly here:
 *  - Files: the workspace's storage tree `workspaces/{external_id}` (knowledge
 *    files, agents' chat files and settings images, members' own files and
 *    chatters' photos) is wiped by prefix on every disk, `local` too, where
 *    data exports live.
 *  - The workspace's Redis chat memory — cache, not DB ({@see self::flushChatMemoryCache()}).
 *  - `model_settings` rows of its agents and members (polymorphic, no FK).
 *  - Sanctum tokens (`personal_access_tokens`, polymorphic — no FK) and HTTP
 *    `sessions` rows (no FK) for the owner and every sub-user.
 *
 * Only a workspace owner reaches here. Each step deletes only what is left, so
 * {@see DeleteWorkspaceJob} can retry it safely.
 */
class DeleteUserAccountAction
{
    /** Audit logs keyed to the workspace, deleted in batches before the final transaction. */
    private const array AUDIT_TABLES = ['agent_activities', 'guardrail_events', 'security_audit_logs'];

    /**
     * The disks a storage prefix is wiped on: the default disk (knowledge
     * files, audio, chat attachments), the assets disk (profile photos,
     * settings images, widget brand assets) and `local`, where data exports
     * live even when the default disk is an S3 bucket.
     */
    private const array STORAGE_DISKS = ['private', 'assets', 'local'];

    /**
     * @param  int  $conversationBatch  conversations per DELETE; each one cascades all its messages
     * @param  int  $rowBatch  chunks, sessions or leads per DELETE
     */
    public function __construct(
        private readonly int $conversationBatch = 500,
        private readonly int $rowBatch = 5000,
    ) {}

    public function execute(User $user): void
    {
        $workspace = $user->workspace();

        // Pre-delete the unbounded children in batches outside the final
        // transaction; the FK cascade then only handles the bounded remainder.
        // Chunks carry vector and BM25 index entries each, so they go first.
        $this->deleteInBatches(
            "DELETE FROM chunks WHERE id IN (SELECT id FROM chunks WHERE workspace_id = ? LIMIT {$this->rowBatch})",
            $workspace,
        );

        // Both chat tables are unique on (workspace_id, id), so each batch
        // reads the next ids straight from that index.
        $this->deleteInBatches(
            "DELETE FROM chat_conversations WHERE id IN (SELECT id FROM chat_conversations WHERE workspace_id = ? ORDER BY id LIMIT {$this->conversationBatch})",
            $workspace,
        );
        $this->deleteInBatches(
            "DELETE FROM chat_sessions WHERE id IN (SELECT id FROM chat_sessions WHERE workspace_id = ? ORDER BY id LIMIT {$this->rowBatch})",
            $workspace,
        );

        // Leads hold the visitor's PII. They would go with the agents anyway,
        // but in one statement; found by agent, which is indexed.
        $this->deleteInBatches(
            "DELETE FROM form_submissions WHERE id IN (SELECT id FROM form_submissions WHERE agent_id IN (SELECT id FROM agents WHERE workspace_id = ?) LIMIT {$this->rowBatch})",
            $workspace,
        );

        // The audit logs would go with the workspace by cascade, but a busy
        // workspace has many rows; each table has an index that starts with
        // workspace_id.
        foreach (self::AUDIT_TABLES as $table) {
            $this->deleteInBatches(
                "DELETE FROM {$table} WHERE id IN (SELECT id FROM {$table} WHERE workspace_id = ? LIMIT {$this->rowBatch})",
                $workspace,
            );
        }

        // Capture the storage root BEFORE the teardown so it can be wiped once
        // the rows are gone: everything the workspace holds, its members' and
        // chatters' files included, lives under workspaces/{external_id}/.
        $workspaceExternalId = $workspace->external_id;
        $storageRoot = $workspace->storageRoot();

        DB::transaction(function () use ($workspace): void {
            $teamIds = $workspace->teams()->pluck('id')->all();

            // Every staff account of the workspace, owner included. Chatters
            // go with the workspace by FK cascade.
            $allUserIds = $workspace->members()->pluck('users.id')->all();

            $this->deleteModelSettings($workspace, $allUserIds);

            // Clear team membership first: team_user.team_id, .user_id and
            // .role_id are all RESTRICT, so the pivot blocks both deleting the
            // workspace's users and the owned-team cascade (team → roles would
            // trip team_user.role_id) until these rows are gone.
            DB::table('team_user')
                ->whereIn('team_id', $teamIds)
                ->orWhereIn('user_id', $allUserIds)
                ->delete();

            // Nothing cascades these, so the deleted users would orphan them:
            // Sanctum tokens are polymorphic (no FK) and the sessions table
            // carries no FK to users either.
            DB::table('personal_access_tokens')
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $allUserIds)
                ->delete();
            DB::table('sessions')->whereIn('user_id', $allUserIds)->delete();

            // The staff accounts — model delete (not a bulk query) so the
            // HasRoles cleanup and other model events fire.
            User::query()->whereKey($allUserIds)->get()->each(fn (User $member) => $member->delete());

            // Finally the workspace; every remaining reference cascades.
            $workspace->delete();
        });

        $this->wipeWorkspaceStorage($storageRoot);
        $this->flushChatMemoryCache($workspaceExternalId);
    }

    /**
     * Settings rows are polymorphic (no foreign key), so nothing cascades
     * them: the agents' rows (deleted agents waiting for their purge
     * included, hence the plain table) and the members' own.
     *
     * @param  list<int>  $userIds
     */
    private function deleteModelSettings(Workspace $workspace, array $userIds): void
    {
        DB::table('model_settings')
            ->where('model_type', (new Agent)->getMorphClass())
            ->whereIn('model_id', fn ($query) => $query->select('id')->from('agents')->where('workspace_id', $workspace->id))
            ->delete();

        DB::table('model_settings')
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn('model_id', $userIds)
            ->delete();
    }

    /**
     * Drop the workspace's Redis chat memory in one shot. Every chat-cache
     * entry carries the workspace tag ({@see ChatCache::workspaceTag()}), so a
     * single tag flush clears conversation lists, message/turn caches, source
     * caches and the `forever` ownership markers that would otherwise outlive
     * the deleted DB rows (they never expire). Best-effort: a teardown must not
     * fail because the cache store is briefly unreachable — log and carry on,
     * mirroring how the S3 step tolerates downstream failures.
     */
    private function flushChatMemoryCache(string $workspaceExternalId): void
    {
        try {
            Cache::driver('chat')->tags(ChatCache::workspaceTag($workspaceExternalId))->flush();
        } catch (Throwable $e) {
            Log::warning('Account deletion: failed to flush workspace chat memory cache.', [
                'workspace' => $workspaceExternalId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Wipe the workspace's storage tree (`workspaces/{external_id}/…`) on
     * every disk in {@see self::STORAGE_DISKS}. The FK cascade drops the rows
     * without firing the per-file Eloquent observers, so without this the
     * objects would leak. Best-effort, disk by disk: a teardown must not fail
     * because remote storage is briefly unreachable.
     */
    private function wipeWorkspaceStorage(string $storageRoot): void
    {
        foreach (self::STORAGE_DISKS as $disk) {
            try {
                Storage::disk($disk)->deleteDirectory($storageRoot);
            } catch (Throwable $e) {
                Log::warning('Account deletion: failed to wipe workspace storage tree.', [
                    'disk' => $disk,
                    'prefix' => $storageRoot,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Loop a bounded DELETE of the workspace's rows until it stops matching.
     * Each DELETE runs on its own (no surrounding transaction), so locks and
     * the cascade's work stay small.
     */
    private function deleteInBatches(string $sql, Workspace $workspace): void
    {
        do {
            $deleted = DB::affectingStatement($sql, [$workspace->id]);
        } while ($deleted > 0);
    }
}

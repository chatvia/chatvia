<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Agent\Models\ModelSetting;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

/**
 * Removes everything a deleted agent owns, then the agent row. Runs from
 * {@see PurgeAgentJob} after {@see DeleteAgentAction} soft-deleted the agent.
 *
 * The big tables go first, in small batches, each its own short statement:
 * leads, then conversations (their messages, attachment rows and topics go
 * by cascade), then sessions. Leads go before conversations because a
 * conversation's delete would first set its leads' conversation to NULL,
 * a wasted update per lead. Then the knowledge sources no other
 * live agent uses (deleted through their models, so their observers clean
 * chunks and wipe each source's own storage folder), the agent's settings
 * rows (`model_settings` has no foreign key), its chat memory in Redis and its
 * storage subtree (chat attachments). Knowledge files are never under the
 * agent's subtree, so a source another agent still uses keeps its files.
 * Tool-call audit rows and guardrail events are kept for the workspace's
 * records: their `agent_id` is cleared in batches too, because the foreign
 * key's SET NULL would otherwise rewrite them all in the final statement.
 * The final `forceDelete()` then only cascades small tables.
 *
 * Every step deletes only what is left, so running it again after a failure
 * is safe. The Redis flush and the storage wipe are best-effort: a short
 * outage must not keep the agent row alive, and the daily prunes are the
 * backstop for leftovers.
 */
class PurgeAgentAction
{
    /** @var list<class-string<Model>> */
    private const array SOURCE_TYPES = [SourceFile::class, SourceWebsite::class, SourceText::class];

    /** Tables whose rows outlive the agent: the foreign key sets their `agent_id` to NULL. */
    private const array DETACHED_TABLES = ['agent_activities', 'guardrail_events'];

    /**
     * @param  int  $conversationBatch  conversations per DELETE; each one cascades all its messages
     * @param  int  $rowBatch  sessions or leads per DELETE, audit or guardrail rows per UPDATE
     */
    public function __construct(
        private readonly int $conversationBatch = 500,
        private readonly int $rowBatch = 5000,
    ) {}

    public function execute(Agent $agent): void
    {
        if (! $agent->trashed()) {
            throw new LogicException('Only a deleted agent can be purged.');
        }

        $this->deleteInBatches('form_submissions', $agent, $this->rowBatch);
        $this->deleteInBatches('chat_conversations', $agent, $this->conversationBatch);
        $this->deleteInBatches('chat_sessions', $agent, $this->rowBatch);

        $this->deleteExclusiveSources($agent);

        ModelSetting::query()
            ->where('model_type', $agent->getMorphClass())
            ->where('model_id', $agent->id)
            ->delete();

        $this->flushChatMemory($agent);

        $this->wipeStorage($agent);

        foreach (self::DETACHED_TABLES as $table) {
            $this->detachInBatches($table, $agent);
        }

        $agent->forceDelete();
    }

    /**
     * Delete the agent's rows of `$table` in batches until none are left. Each
     * DELETE runs on its own (no surrounding transaction), so locks and the
     * cascade's work stay small. The three tables have an index that starts
     * with agent_id, so each batch reads the next rows straight from it; no
     * order is needed, as every row goes. The (workspace_id, agent_id) pair
     * is the tables' foreign key to the agent.
     */
    private function deleteInBatches(string $table, Agent $agent, int $batchSize): void
    {
        $sql = "DELETE FROM {$table} WHERE id IN (SELECT id FROM {$table} WHERE agent_id = ? AND workspace_id = ? LIMIT {$batchSize})";

        do {
            $deleted = DB::affectingStatement($sql, [$agent->id, $agent->workspace_id]);
        } while ($deleted > 0);
    }

    /**
     * Clear the agent on its rows of `$table` in batches, the same way the
     * deletes above run, so the final delete has nothing left to set to NULL.
     * Both tables have an (agent_id, id) index for the batch lookup.
     */
    private function detachInBatches(string $table, Agent $agent): void
    {
        $sql = "UPDATE {$table} SET agent_id = NULL WHERE id IN (SELECT id FROM {$table} WHERE agent_id = ? AND workspace_id = ? LIMIT {$this->rowBatch})";

        do {
            $updated = DB::affectingStatement($sql, [$agent->id, $agent->workspace_id]);
        } while ($updated > 0);
    }

    /**
     * Sources attached only to this agent, found in SQL (one query per source
     * type). Each is deleted through its model so its own observer cleans its
     * storage and chunks. This agent's `agent_sources` rows go with the agent
     * (cascading foreign key).
     */
    private function deleteExclusiveSources(Agent $agent): void
    {
        foreach (self::SOURCE_TYPES as $type) {
            $ids = AgentSource::exclusiveSourceIds($agent->id, $type);

            if ($ids->isNotEmpty()) {
                $type::query()->whereIn('id', $ids)->cursor()->each(fn (Model $source) => $source->delete());
            }
        }
    }

    /**
     * Every session-scoped chat-memory entry carries the agent tag
     * ({@see ChatCache::agentTag()}), so one flush clears the conversation
     * lists, message/turn/source caches and session data together.
     */
    private function flushChatMemory(Agent $agent): void
    {
        try {
            Cache::driver('chat')->tags(ChatCache::agentTag($agent->external_id))->flush();
        } catch (Throwable $e) {
            Log::warning('Agent purge: failed to flush agent chat memory cache.', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The agent's whole storage subtree, keyed entity-first by external id:
     * its chats' files (uploads, generated media, replies read aloud), setting
     * images and anything else written for the agent alone. Knowledge sources
     * live in their own folders, outside this one.
     */
    private function wipeStorage(Agent $agent): void
    {
        $prefix = $agent->settingsStorageRoot();

        try {
            Storage::deleteDirectory($prefix);
            // Setting images (widget logo, avatar) are on the CDN-backed disk.
            Storage::disk('assets')->deleteDirectory($prefix);
        } catch (Throwable $e) {
            Log::warning('Agent purge: failed to wipe agent storage subtree.', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

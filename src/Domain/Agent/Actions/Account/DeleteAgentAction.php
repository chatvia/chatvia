<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes an agent with everything that belongs only to it: the knowledge
 * sources no other agent uses (shared ones stay), its visitor sessions (and by
 * cascade their conversations), its chat memory in Redis and its storage
 * subtree. The database rows go in one transaction; the Redis flush and the
 * storage wipe run after the commit and are best-effort — a short outage must
 * not block the delete, and the daily prune is the backstop for leftovers.
 * Used by the dashboard and the public API.
 */
class DeleteAgentAction
{
    /** @var list<class-string<Model>> */
    private const array SOURCE_TYPES = [SourceFile::class, SourceWebsite::class, SourceText::class, SourceYoutubeVideo::class];

    public function execute(Agent $agent): void
    {
        // Resolved up front: the owner row is still there before the delete.
        $workspaceExternalId = $agent->user()->value('external_id');

        DB::transaction(function () use ($agent): void {
            $this->deleteExclusiveSources($agent);

            Session::query()->where('agent_id', $agent->id)->delete();

            $agent->delete();
        });

        $this->flushChatMemory($agent);

        if ($workspaceExternalId !== null) {
            $this->wipeStorage($agent, $workspaceExternalId);
        }
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
            Log::warning('Agent deletion: failed to flush agent chat memory cache.', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The agent's whole storage subtree, keyed entity-first by external id:
     * knowledge files, audio, websites, text and chunk images on the default
     * disk, plus chat attachments on the public disk.
     */
    private function wipeStorage(Agent $agent, string $workspaceExternalId): void
    {
        $prefix = 'users/'.$workspaceExternalId.'/agents/'.$agent->external_id;

        try {
            Storage::deleteDirectory($prefix);
            Storage::disk('public')->deleteDirectory($prefix);
        } catch (Throwable $e) {
            Log::warning('Agent deletion: failed to wipe agent storage subtree.', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

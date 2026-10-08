<?php

namespace Domain\Agent\Observers;

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Agent\Services\AgentLocator as AgentHelper;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AgentObserver
{
    /**
     * Seed the JWT signing secret as part of the initial insert so the
     * read-only identity/preview render paths never trigger a lazy write
     * ({@see Agent::ensureAuthSecret()}). Rotation stays explicit via
     * {@see Agent::regenerateAuthSecret()}.
     */
    public function creating(Agent $agent): void
    {
        if (empty($agent->auth_secret)) {
            $agent->auth_secret = Agent::generateAuthSecret();
        }
    }

    public function created(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);

        // Each agent answers with a single LLM. Seed it to the platform's
        // configured default (config('chatvia.agent.default_model'), matched by
        // `llms.internal_name`); fall back to the first available row if the
        // configured name isn't in the catalog. Operators can swap it in
        // Setup later.
        if ($agent->llm_id === null) {
            $llms = $agent->user->llms();
            $defaultName = (string) config('chatvia.agent.default_model');
            $llmId = $llms->firstWhere('internal_name', $defaultName)?->id ?? $llms->first()?->id;

            if ($llmId !== null) {
                $agent->forceFill(['llm_id' => $llmId])->save();
            }
        }
    }

    public function updated(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);
    }

    /**
     * Handle the Agent "deleting" event.
     *
     * Resolves "sources attached only to this agent" in SQL (one EXISTS query
     * per source type) instead of N×(count) per-row loops. Sources still
     * shared with other agents are left intact; the agent_sources rows for
     * this agent are cleaned up by the cascadeOnDelete FK on agent_id.
     */
    public function deleting(Agent $agent): void
    {
        foreach ([SourceFile::class, SourceWebsite::class, SourceText::class, SourceYoutubeVideo::class] as $type) {
            $exclusiveIds = AgentSource::exclusiveSourceIds($agent->id, $type);

            if ($exclusiveIds->isNotEmpty()) {
                // Use ->each(fn) on the eloquent set so per-model observers
                // (storage cleanup, chunk cascade) still fire for each row.
                $type::whereIn('id', $exclusiveIds)
                    ->cursor()
                    ->each(fn ($model) => $model->delete());
            }
        }

        // Drop this agent's Redis chat memory in one shot. Every session-scoped
        // entry carries the agent tag ({@see ChatCache::agentTag()}), so a
        // single flush clears the conversation lists, message/turn/source caches
        // and session-data entries together — reaching the per-message and
        // per-source caches the old per-session forget never touched. Best
        // effort: a transient cache outage must not block deleting the agent
        // (the rows below are the source of truth), mirroring the workspace
        // teardown in DeleteUserAccountAction.
        try {
            Cache::driver('chat')->tags(ChatCache::agentTag($agent->external_id))->flush();
        } catch (Throwable $e) {
            Log::warning('Agent deletion: failed to flush agent chat memory cache.', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }

        Session::query()->where('agent_id', $agent->id)->delete();
    }

    public function deleted(Agent $agent): void
    {
        // Wipe the agent's whole storage subtree in one shot — knowledge files,
        // audio, websites, text and chunk images on the default disk, plus chat
        // attachments on the public disk. Keyed entity-first by external id.
        // Skip when the owning workspace is already gone: the account-deletion
        // teardown wipes the entire users/{external_id} tree itself.
        $workspaceExternalId = $agent->user()->value('external_id');
        if ($workspaceExternalId === null) {
            return;
        }

        $prefix = 'users/'.$workspaceExternalId.'/agents/'.$agent->external_id;

        // Best-effort, like the cache flush above — a delete must not fail
        // because remote storage is briefly unreachable. The daily attachment
        // prune is the backstop for anything left behind.
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

    public function restored(Agent $agent): void {}

    /**
     * Handle the Agent "force deleted" event.
     */
    public function forceDeleted(Agent $agent): void
    {
        $this->deleted($agent);
    }
}

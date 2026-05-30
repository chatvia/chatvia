<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentSource;
use Domain\Agents\Models\Llm;
use Domain\Agents\Services\Agent as AgentHelper;
use Domain\Chat\Models\ChatSession;
use Domain\Sources\Files\Models\SourceFile;
use Domain\Sources\Text\Models\SourceText;
use Domain\Sources\Web\Models\SourceWebsite;
use Domain\Sources\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Facades\Storage;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);

        // Every agent exposes Hosted Chat / Widget / Bar surfaces; each surface
        // picks its own model(s) in setup. Attach every LLM the owner has access
        // to so per-surface model pickers have the full catalogue to choose from.
        $llmIds = $agent->user->llms()->pluck('id')->toArray();

        $agent->llms()->attach($llmIds);
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

        $memory = app(\Domain\Chat\Services\Memory\ChatSession::class);

        ChatSession::query()
            ->where('agent_id', $agent->id)
            ->select(['id'])
            ->cursor()
            ->each(function (ChatSession $session) use ($memory): void {
                $memory->deleteSession($session->id);
            });

        ChatSession::query()->where('agent_id', $agent->id)->delete();
    }

    public function deleted(Agent $agent): void
    {
        Storage::deleteDirectory('users/'.$agent->user_id.'/agents/'.$agent->id);
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

<?php

namespace Domain\Agent\Observers;

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Agent\Services\Agent as AgentHelper;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\TurnState;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            $agent->auth_secret = 'cvsk_'.Str::random(56);
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

        $memory = app(TurnState::class);

        Session::query()
            ->where('agent_id', $agent->id)
            ->select(['id'])
            ->cursor()
            ->each(function (Session $session) use ($memory): void {
                $memory->deleteSession($session->id);
            });

        Session::query()->where('agent_id', $agent->id)->delete();
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

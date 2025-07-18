<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\Llm;
use App\Models\User;
use App\Support\Agent as AgentHelper;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);

        /** @var User $user */
        $user = $agent->user;

        $llms = $user->llms();

        $agentLlms = $llms->pluck('id')->toArray();

        $agent->llms()->attach(
            Llm::query()
                ->select('id')
                ->whereIn('id', $agentLlms)
                ->get()
                ->pluck('id')
                ->toArray()
        );
    }

    public function updated(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);
    }

    public function deleted(Agent $agent): void {}

    public function restored(Agent $agent): void
    {
        //
    }

    /**
     * Handle the Agent "force deleted" event.
     */
    public function forceDeleted(Agent $agent): void
    {
        //
    }
}

<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\Llm;
use App\Models\User;
use App\Support\Agent as AgentHelper;
use Illuminate\Support\Facades\DB;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);

        /** @var User $user */
        $user = $agent->user;

        $llms = $user->llms();

        $agentLlms = $llms->pluck('id')->toArray();

        $agent
            ->llms()
            ->attach(
                Llm::query()
                    ->select('id')
                    ->whereIn('id', $agentLlms)
                    ->get()
                    ->pluck('id')
                    ->toArray()
            );

        $this->decreaseAgentLimit($agent);
    }

    public function updated(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);
    }

    public function deleted(Agent $agent): void
    {
        $this->increaseAgentLimit($agent);
    }

    public function restored(Agent $agent): void
    {
        //
    }

    /**
     * Handle the Agent "force deleted" event.
     */
    public function forceDeleted(Agent $agent): void
    {
        $this->deleted($agent);
    }

    private function decreaseAgentLimit(Agent $agent): void
    {
        $this->updateAgentQuota($agent, false);
    }

    private function increaseAgentLimit(Agent $agent): void
    {
        $this->updateAgentQuota($agent, true);
    }

    private function updateAgentQuota(Agent $agent, bool $increment): void
    {
        /** @var User $user */
        $user = $agent->user;

        $agentTypeField = $agent->isKnowledgeable() ? 'agents_knowledge_limit' : 'agents_instruction_limit';

        $operator = $increment ? '+' : '-';

//        $user->update([
//            $agentTypeField => DB::raw($agentTypeField.' '.$operator.' 1'),
//        ]);
    }
}

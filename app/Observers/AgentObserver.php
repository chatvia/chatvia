<?php

namespace App\Observers;

use App\Enums\PermissionEnum;
use App\Models\Agent;
use App\Models\Llm;
use App\Models\User;
use App\Support\Agent as AgentHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);

        /** @var User $user */
        $user = $agent->user;

        $defaultLlm = Llm::query()
            ->select('id')
            ->where('is_default', true)
            ->value('id');

        if ($user->hasPermission(PermissionEnum::MODELS_VIEW)) {
            $llms = $user->llms();

            $agentLlms = $agent->isStandalone() ? $llms->pluck('id')->toArray() : [$defaultLlm];

            $llmIds = Llm::query()
                ->select('id')
                ->whereIn('id', $agentLlms)
                ->get()
                ->pluck('id')
                ->toArray();

        } else {
            $llmIds = [$defaultLlm];
        }

        $agent
            ->llms()
            ->attach(
                $llmIds
            );
    }

    public function updated(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);
    }

    public function deleted(Agent $agent): void
    {
        Storage::deleteDirectory('users/'.$agent->user_id.'/agents/'.$agent->id);
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

        $agentTypeField = 'agents_limit'; // Unified agents limit

        $operator = $increment ? '+' : '-';

        //        $user->update([
        //            $agentTypeField => DB::raw($agentTypeField.' '.$operator.' 1'),
        //        ]);
    }
}

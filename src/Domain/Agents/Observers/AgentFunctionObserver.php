<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\AgentFunction;
use Domain\Chat\Jobs\EmbedFunctionJob;
use Domain\Chat\Services\Tooling\ToolRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Functions are account-level, attached to agents via
 * `agent_function`. Cache invalidation walks that pivot — one
 * function update can affect many agents.
 *
 * Also drives the semantic-router embedding refresh: any time the
 * name or description changes (the fields the router scores against),
 * a queued job re-embeds so the row scores correctly on the next turn.
 */
class AgentFunctionObserver
{
    public function __construct(private readonly ToolRegistry $registry) {}

    public function created(AgentFunction $function): void
    {
        EmbedFunctionJob::dispatch($function->id);
    }

    public function updated(AgentFunction $function): void
    {
        if ($function->wasChanged(['name', 'description'])) {
            EmbedFunctionJob::dispatch($function->id);
        }
        $this->forgetForAllAttachedAgents($function);
    }

    public function deleted(AgentFunction $function): void
    {
        $this->forgetForAllAttachedAgents($function);
    }

    private function forgetForAllAttachedAgents(AgentFunction $function): void
    {
        $agentIds = DB::table('agent_function')
            ->where('function_id', $function->id)
            ->pluck('agent_id');

        foreach ($agentIds as $agentId) {
            $this->registry->forget((int) $agentId);
        }
    }
}

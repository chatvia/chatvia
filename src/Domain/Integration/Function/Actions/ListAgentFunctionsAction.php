<?php

namespace Domain\Integration\Function\Actions;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Data\Resources\AgentFunctionRowData;
use Domain\Integration\Function\Data\Resources\AgentFunctionsListResourceData;
use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\DataCollection;

/**
 * List every workspace function alongside whether a specific agent uses it —
 * the same shape as the agent's connectors list.
 */
class ListAgentFunctionsAction
{
    public function execute(Agent $agent): AgentFunctionsListResourceData
    {
        // Functions belong to the workspace owner, who also owns the agent.
        $functions = AgentFunction::query()
            ->where('workspace_id', $agent->workspace_id)
            ->orderBy('internal_name')
            ->get();

        $attachedIds = $agent->functions()->pluck('functions.id')->flip();

        $rows = $functions->map(
            fn (AgentFunction $function): AgentFunctionRowData => AgentFunctionRowData::fromModel(
                $function,
                $attachedIds->has($function->id),
            ),
        );

        return new AgentFunctionsListResourceData(
            data: new DataCollection(AgentFunctionRowData::class, $rows),
        );
    }
}

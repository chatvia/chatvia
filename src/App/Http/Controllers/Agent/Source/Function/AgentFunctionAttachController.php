<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Data\Resources\AgentFunctionAttachResultData;
use Domain\Integration\Function\Data\Resources\AgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * Attach one of the workspace's webhook functions to this agent. Access is
 * gated by the route middleware; the function must belong to the agent's
 * workspace.
 */
class AgentFunctionAttachController
{
    public function __invoke(Agent $agent, AgentFunction $function): AgentFunctionAttachResultData
    {
        abort_unless($agent->ownsFunction($function), 404);

        $agent->attachFunction($function);

        return new AgentFunctionAttachResultData(
            data: AgentFunctionData::fromModel($function->fresh()),
        );
    }
}

<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;

class DetachFunctionFromAgentAction
{
    public function execute(Agent $agent, AgentFunction $function): void
    {
        $agent->functions()->detach($function->id);
    }
}

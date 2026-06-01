<?php

namespace Domain\Agents\Actions\Functions;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;

class AttachFunctionToAgentAction
{
    public function execute(Agent $agent, AgentFunction $function): AgentFunction
    {
        $agent->functions()->syncWithoutDetaching([$function->id]);

        return $function->fresh();
    }
}

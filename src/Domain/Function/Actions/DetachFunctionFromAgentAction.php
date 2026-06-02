<?php

namespace Domain\Function\Actions;

use Domain\Agent\Models\Agent;
use Domain\Function\Models\AgentFunction;

class DetachFunctionFromAgentAction
{
    public function execute(Agent $agent, AgentFunction $function): void
    {
        $agent->functions()->detach($function->id);
    }
}

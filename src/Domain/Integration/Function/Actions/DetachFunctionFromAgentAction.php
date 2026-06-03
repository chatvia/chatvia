<?php

namespace Domain\Integration\Function\Actions;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;

class DetachFunctionFromAgentAction
{
    public function execute(Agent $agent, AgentFunction $function): void
    {
        $agent->functions()->detach($function->id);
    }
}

<?php

namespace Domain\Integration\Function\Actions;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;

class AttachFunctionToAgentAction
{
    public function execute(Agent $agent, AgentFunction $function): AgentFunction
    {
        $agent->functions()->syncWithoutDetaching([$function->id]);

        return $function->fresh();
    }
}

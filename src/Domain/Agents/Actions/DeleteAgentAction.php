<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class DeleteAgentAction
{
    public function execute(Agent $agent): void
    {
        $agent->delete();
    }
}

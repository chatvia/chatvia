<?php

namespace Domain\Agents\Actions\Account;

use Domain\Agents\Models\Agent;

class DeleteAgentAction
{
    public function execute(Agent $agent): void
    {
        $agent->delete();
    }
}

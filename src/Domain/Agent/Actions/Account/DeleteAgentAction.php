<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Models\Agent;

class DeleteAgentAction
{
    public function execute(Agent $agent): void
    {
        $agent->delete();
    }
}

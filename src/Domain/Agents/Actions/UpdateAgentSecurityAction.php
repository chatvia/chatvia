<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class UpdateAgentSecurityAction
{
    public function execute(Agent $agent, bool $isPublic): void
    {
        $agent->update(['is_public' => $isPublic]);
    }
}

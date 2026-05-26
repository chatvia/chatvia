<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class DeleteAgentApiTokenAction
{
    public function execute(Agent $agent, int $tokenId): void
    {
        $agent->tokens()->whereKey($tokenId)->delete();
    }
}

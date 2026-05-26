<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class DeleteAgentStarterAction
{
    public function execute(Agent $agent, int $starterId): void
    {
        $agent->starters()->where('id', $starterId)->delete();
    }
}

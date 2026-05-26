<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Starter;

class CreateAgentStarterAction
{
    public function execute(Agent $agent, string $text): Starter
    {
        return $agent->starters()->create(['text' => $text]);
    }
}

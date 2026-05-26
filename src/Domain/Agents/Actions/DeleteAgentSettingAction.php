<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class DeleteAgentSettingAction
{
    public function execute(Agent $agent, string $group, string $key): void
    {
        $agent->settingsDelete($group, $key);
    }
}

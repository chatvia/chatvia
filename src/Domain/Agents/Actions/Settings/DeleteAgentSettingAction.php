<?php

namespace Domain\Agents\Actions\Settings;

use Domain\Agents\Models\Agent;

class DeleteAgentSettingAction
{
    public function execute(Agent $agent, string $group, string $key): void
    {
        $agent->settingsDelete($group, $key);
    }
}

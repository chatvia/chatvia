<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;

class DeleteAgentSettingAction
{
    public function execute(Agent $agent, string $group, string $key): void
    {
        $agent->settingsDelete($group, $key);
    }
}

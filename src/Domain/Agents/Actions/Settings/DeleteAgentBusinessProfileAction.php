<?php

namespace Domain\Agents\Actions\Settings;

use Domain\Agents\Models\Agent;

class DeleteAgentBusinessProfileAction
{
    public function execute(Agent $agent): void
    {
        $agent->settingsDelete('business', ['email', 'phone', 'website', 'address', 'opening_hours']);
    }
}

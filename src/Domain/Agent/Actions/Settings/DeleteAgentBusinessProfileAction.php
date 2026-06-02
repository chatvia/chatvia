<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;

class DeleteAgentBusinessProfileAction
{
    public function execute(Agent $agent): void
    {
        $agent->settingsDelete('business', ['email', 'phone', 'website', 'address', 'opening_hours']);
    }
}

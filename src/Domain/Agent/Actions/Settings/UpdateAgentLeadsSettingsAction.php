<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;

class UpdateAgentLeadsSettingsAction
{
    /**
     * @param  array<string, array{title: string, required: bool}>  $fields
     */
    public function execute(Agent $agent, array $fields): void
    {
        $agent->setSetting('fields', $fields, 'leads');
    }
}

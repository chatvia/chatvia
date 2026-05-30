<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

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

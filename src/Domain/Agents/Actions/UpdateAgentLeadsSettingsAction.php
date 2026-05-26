<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class UpdateAgentLeadsSettingsAction
{
    /**
     * @param  array<int, mixed>  $fields
     */
    public function execute(Agent $agent, bool $collectLeads, string $title, array $fields): void
    {
        $agent->update(['collect_leads' => $collectLeads]);

        $agent->setSetting('title', $title, 'leads');
        $agent->setSetting('fields', $fields, 'leads');
    }
}

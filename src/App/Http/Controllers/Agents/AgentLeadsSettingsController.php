<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Actions\UpdateAgentLeadsSettingsAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentLeadsSettingsController
{
    public function __invoke(Request $request, Agent $agent, UpdateAgentLeadsSettingsAction $update): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.leads.settings'), 403);

        $request->validate([
            'collect_leads' => ['required', 'boolean'],
            'title' => ['required_if:collect_leads,true', 'string'],
            'fields' => ['required_if:collect_leads,true', 'array'],
        ]);

        $update->execute(
            agent: $agent,
            collectLeads: $request->boolean('collect_leads'),
            title: (string) $request->input('title', ''),
            fields: (array) $request->input('fields', []),
        );
    }
}

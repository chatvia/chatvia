<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentLeadsSettingsController
{
    public function __invoke(Request $request, Agent $agent): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.leads.settings'), 403);

        $request->validate([
            'collect_leads' => ['required', 'boolean'],
            'title' => ['required_if:collect_leads,true', 'string'],
            'fields' => ['required_if:collect_leads,true', 'array'],
        ]);

        $agent->update(['collect_leads' => $request->input('collect_leads')]);

        $agent->setSetting('title', $request->input('title'), 'leads');
        $agent->setSetting('fields', $request->input('fields'), 'leads');
    }
}

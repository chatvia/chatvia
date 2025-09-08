<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentLeadsUpdateSettingsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $request->validate([
            'collect_leads' => ['required', 'boolean'],
            'title' => ['required_if:collect_leads,true', 'string'],
            'fields' => ['required_if:collect_leads,true', 'array'],
        ]);

        $agent->update([
            'collect_leads' => $request->input('collect_leads'),
        ]);

        $agent->setSetting('title', $request->input('title'), 'leads');
        $agent->setSetting('fields', $request->input('fields'), 'leads');
    }
}

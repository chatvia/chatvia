<?php

namespace App\Http\Controllers\Agents\Leads;

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
            'fields' => ['required', 'array'],
            'fields.*.title' => ['required', 'string'],
            'fields.*.required' => ['required', 'boolean'],
        ]);

        $update->execute(
            agent: $agent,
            fields: (array) $request->input('fields', []),
        );
    }
}

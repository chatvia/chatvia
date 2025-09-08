<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentLeadsDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $agent),
            code: 404,
        );

        $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $leadIds = $request->input('lead_ids');

        $agent->leads()->whereIn('id', $leadIds)->delete();

        return back()->with('success', 'Selected leads have been deleted successfully.');
    }
}

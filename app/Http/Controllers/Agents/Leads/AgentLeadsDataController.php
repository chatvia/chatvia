<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Data\Resources\Agent\LeadResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentLeadsDataController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $leads = $agent->leads()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($lead) => LeadResourceData::fromModel($lead));

        return response()->json($leads);
    }
}

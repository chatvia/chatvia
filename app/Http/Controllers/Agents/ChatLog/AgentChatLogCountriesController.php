<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\ChatSession;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentChatLogCountriesController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        // Get country IDs that have conversations for this agent
        $countryIds = ChatSession::query()
            ->where('agent_id', $agent->id)
            ->whereNotNull('country_id')
            ->distinct()
            ->pluck('country_id');

        // Get country details for these IDs
        $countries = Country::query()
            ->whereIn('id', $countryIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json([
            'countries' => $countries,
        ]);
    }
}

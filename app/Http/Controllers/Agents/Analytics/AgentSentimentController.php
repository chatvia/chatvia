<?php

namespace App\Http\Controllers\Agents\Analytics;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentSentimentController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Sentiment', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}

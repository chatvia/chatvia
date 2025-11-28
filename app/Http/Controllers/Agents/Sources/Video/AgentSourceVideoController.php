<?php

namespace App\Http\Controllers\Agents\Sources\Video;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentSourceVideoController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Sources/Video', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}

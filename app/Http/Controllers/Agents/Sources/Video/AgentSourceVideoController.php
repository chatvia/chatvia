<?php

namespace App\Http\Controllers\Agents\Sources\Video;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentSourceVideoController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Video', [
            'agent' => $agent,
        ]);
    }
}

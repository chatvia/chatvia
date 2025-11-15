<?php

namespace App\Http\Controllers\Agents\Sources\Audio;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentSourceAudioController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Audio', [
            'agent' => $agent,
        ]);
    }
}

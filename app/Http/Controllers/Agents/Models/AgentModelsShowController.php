<?php

namespace App\Http\Controllers\Agents\Models;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentModelsShowController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Models', [
            'agent' => $agent,
            //            'currentSettings' => [
            //                'selected_models' => $agent->getSetting('selected_models', []),
            //                'temperature' => $agent->getSetting('temperature', 0.7),
            //                'top_k' => $agent->getSetting('top_k', 40),
            //                'top_p' => $agent->getSetting('top_p', 0.9),
            //                'max_tokens' => $agent->getSetting('max_tokens', 2048),
            //                'presence_penalty' => $agent->getSetting('presence_penalty', 0),
            //                'frequency_penalty' => $agent->getSetting('frequency_penalty', 0),
            //            ],
        ]);
    }
}

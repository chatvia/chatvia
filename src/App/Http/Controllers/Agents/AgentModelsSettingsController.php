<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentModelsSettingsController
{
    public function __invoke(Request $request, Agent $agent): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $request->validate([
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_k' => ['required', 'integer', 'min:0', 'max:100'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'presence_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
        ]);

        $agent->llms()->updateExistingPivot($agent->llms()->pluck('llms.id'), [
            'temperature' => $request->input('temperature'),
            'top_k' => $request->input('top_k'),
            'top_p' => $request->input('top_p'),
            'presence_penalty' => $request->input('presence_penalty'),
            'frequency_penalty' => $request->input('frequency_penalty'),
        ]);
    }
}

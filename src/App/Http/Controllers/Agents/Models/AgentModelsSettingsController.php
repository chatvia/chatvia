<?php

namespace App\Http\Controllers\Agents\Models;

use Domain\Agents\Actions\UpdateAgentLlmSettingsAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentModelsSettingsController
{
    public function __invoke(Request $request, Agent $agent, UpdateAgentLlmSettingsAction $update): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $request->validate([
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_k' => ['required', 'integer', 'min:0', 'max:100'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'presence_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
        ]);

        $update->execute(
            agent: $agent,
            temperature: (float) $request->input('temperature'),
            topK: (int) $request->input('top_k'),
            topP: (float) $request->input('top_p'),
            presencePenalty: (float) $request->input('presence_penalty'),
            frequencyPenalty: (float) $request->input('frequency_penalty'),
        );
    }
}

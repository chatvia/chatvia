<?php

namespace App\Http\Controllers\Agent\Persona;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The agent's Persona page: its instructions and the workspace's saved
 * prompts to choose from.
 */
class AgentShowPersonaController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $agent->loadMissing('agentPrompt');

        return Inertia::render('agents/Persona', [
            'agent' => AgentResourceData::fromModel($agent),
            'prompts' => $request->user()->workspace()->prompts()
                ->latest()
                ->get()
                ->map(fn (AgentPrompt $p): array => [
                    'id' => $p->external_id,
                    'name' => $p->name,
                    'prompt' => $p->prompt,
                ])
                ->all(),
        ]);
    }
}

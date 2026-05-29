<?php

namespace App\Http\Controllers\Agents\Persona;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentPrompt;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentShowPersonaController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $agent->loadMissing('agentPrompt');

        return Inertia::render('agents/Persona', [
            'agent' => AgentResourceData::fromModel($agent),
            'prompts' => $request->user()->prompts()
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

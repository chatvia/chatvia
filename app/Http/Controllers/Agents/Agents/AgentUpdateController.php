<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentUpdateController
{
    public function __invoke(Agent $agent, Request $request): JsonResponse
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'sub_domain' => ['sometimes', 'nullable', 'string', 'min:3', 'max:255', 'alpha_dash'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $agent->update($validated);

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers\Agent\Starters;

use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentStarterController
{
    public function update(Request $request, Agent $agent, SetAgentStartersAction $setStarters): JsonResponse
    {
        $validated = $request->validate([
            'starters' => ['array'],
            'starters.*.id' => ['nullable', 'string', 'max:64'],
            'starters.*.text' => ['required', 'string', 'max:255'],
            'starters.*.icon' => ['nullable', 'string', 'max:64'],
            'starters.*.overrides' => ['nullable', 'array'],
            'starters.*.overrides.*' => ['nullable', 'string', 'max:255'],
        ]);

        // Saving auto-translates each starter into every language; the fresh
        // items (with translations) go back so the flag tabs populate without a
        // reload.
        $starters = $setStarters->execute($agent, $validated['starters'] ?? []);

        return response()->json(['starters' => $starters]);
    }
}

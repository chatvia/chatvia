<?php

namespace App\Http\Controllers\Agent\Widget;

use Domain\Agent\Actions\Settings\SetWidgetBubblesAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentBubbleController
{
    public function update(Request $request, Agent $agent, SetWidgetBubblesAction $setBubbles): JsonResponse
    {
        $validated = $request->validate([
            'bubbles' => ['array'],
            'bubbles.*.text' => ['required', 'string', 'max:255'],
            'bubbles.*.overrides' => ['nullable', 'array'],
            'bubbles.*.overrides.*' => ['nullable', 'string', 'max:255'],
        ]);

        // Saving auto-translates each bubble into every enabled language; the
        // enriched items go back so the flag tabs populate without a reload.
        $bubbles = $setBubbles->execute($agent, $validated['bubbles'] ?? []);

        return response()->json(['bubbles' => $bubbles]);
    }
}

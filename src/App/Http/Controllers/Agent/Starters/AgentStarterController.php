<?php

namespace App\Http\Controllers\Agent\Starters;

use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

class AgentStarterController
{
    public function update(Request $request, Agent $agent, SetAgentStartersAction $setStarters): void
    {
        $validated = $request->validate([
            'starters' => ['array'],
            'starters.*.text' => ['required', 'string', 'max:255'],
            'starters.*.icon' => ['nullable', 'string', 'max:64'],
        ]);

        $setStarters->execute($agent, $validated['starters'] ?? []);
    }
}

<?php

namespace App\Http\Controllers\Agent\Starters;

use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

class AgentStarterController
{
    public function update(Request $request, Agent $agent, SetAgentStartersAction $setStarters): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $validated = $request->validate([
            'text' => ['array'],
            'text.*' => ['string', 'max:255'],
        ]);

        $setStarters->execute($agent, $validated['text'] ?? []);
    }
}

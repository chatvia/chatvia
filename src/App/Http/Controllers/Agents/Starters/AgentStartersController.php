<?php

namespace App\Http\Controllers\Agents\Starters;

use Domain\Agents\Actions\SetAgentStartersAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentStartersController
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

<?php

namespace App\Http\Controllers\Agent\Starters;

use App\Http\Requests\Agent\Starters\UpdateAgentStartersRequest;
use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;

/**
 * Saves the agent's conversation starters. Saving auto-translates each
 * starter into every enabled language; the stored items (with their
 * translations) go back so the language tabs fill in without a reload.
 */
class AgentStarterController
{
    public function update(UpdateAgentStartersRequest $request, Agent $agent, SetAgentStartersAction $setStarters): JsonResponse
    {
        return response()->json(['starters' => $setStarters->execute($agent, $request->toData())]);
    }
}

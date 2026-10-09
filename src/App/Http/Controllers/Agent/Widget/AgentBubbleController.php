<?php

namespace App\Http\Controllers\Agent\Widget;

use App\Http\Requests\Agent\Widget\UpdateWidgetBubblesRequest;
use Domain\Agent\Actions\Settings\SetWidgetBubblesAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;

/**
 * Saves the agent's widget chat-bubble prompts. Saving auto-translates each
 * bubble into every enabled language; the stored items go back so the
 * language tabs fill in without a reload.
 */
class AgentBubbleController
{
    public function update(UpdateWidgetBubblesRequest $request, Agent $agent, SetWidgetBubblesAction $setBubbles): JsonResponse
    {
        return response()->json(['bubbles' => $setBubbles->execute($agent, $request->toData())]);
    }
}

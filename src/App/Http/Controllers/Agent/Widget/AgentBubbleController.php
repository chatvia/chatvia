<?php

namespace App\Http\Controllers\Agent\Widget;

use App\Http\Requests\Agent\Widget\UpdateWidgetBubblesRequest;
use Domain\Agent\Actions\Settings\SetWidgetBubblesAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;

class AgentBubbleController
{
    public function update(UpdateWidgetBubblesRequest $request, Agent $agent, SetWidgetBubblesAction $setBubbles): JsonResponse
    {
        // Saving auto-translates each bubble into every enabled language; the
        // enriched items go back so the flag tabs populate without a reload.
        $bubbles = $setBubbles->execute($agent, $request->bubbles());

        return response()->json(['bubbles' => $bubbles]);
    }
}

<?php

namespace App\Http\Controllers\Agent\Analytics\Tone;

use App\Http\Requests\Agent\Analytics\AnalyticsDateRangeRequest;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Dimensions\BuildMessageDimensionBreakdownAction;
use Domain\Analytics\Enums\MessageDimension;
use Illuminate\Http\JsonResponse;

/**
 * Daily visitor tone for the agent's tone chart.
 */
class AgentToneDataController
{
    public function __invoke(AnalyticsDateRangeRequest $request, Agent $agent, BuildMessageDimensionBreakdownAction $breakdown): JsonResponse
    {
        $result = $breakdown->execute($agent, MessageDimension::Tone, $request->toData());

        return response()->json([
            'toneByDate' => $result->byDate,
            'totalTones' => $result->totals,
        ]);
    }
}

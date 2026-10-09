<?php

namespace App\Http\Controllers\Agent\Analytics\Sentiment;

use App\Http\Requests\Agent\Analytics\AnalyticsDateRangeRequest;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Dimensions\BuildMessageDimensionBreakdownAction;
use Domain\Analytics\Enums\MessageDimension;
use Illuminate\Http\JsonResponse;

/**
 * Daily visitor sentiment for the agent's sentiment chart.
 */
class AgentSentimentDataController
{
    public function __invoke(AnalyticsDateRangeRequest $request, Agent $agent, BuildMessageDimensionBreakdownAction $breakdown): JsonResponse
    {
        $result = $breakdown->execute($agent, MessageDimension::Sentiment, $request->toData());

        return response()->json([
            'sentimentByDate' => $result->byDate,
            'totalSentiments' => $result->totals,
        ]);
    }
}

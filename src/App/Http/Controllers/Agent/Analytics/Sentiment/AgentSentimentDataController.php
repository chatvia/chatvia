<?php

namespace App\Http\Controllers\Agent\Analytics\Sentiment;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\AgentMessageDimensionByDateAction;
use Domain\Analytics\Data\DateRangeData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSentimentDataController
{
    public function __invoke(Request $request, Agent $agent, AgentMessageDimensionByDateAction $aggregate): JsonResponse
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $range = DateRangeData::fromRequestOrLastDays($request);

        $result = $aggregate->execute(
            agent: $agent,
            column: 'sentiment',
            bucketValues: ['positive', 'negative', 'neutral'],
            startDate: $range->start,
            endDate: $range->end,
        );

        return response()->json([
            'sentimentByDate' => $result['byDate'],
            'totalSentiments' => $result['totals'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Agent\Analytics\Tone;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\AgentMessageDimensionByDateAction;
use Domain\Analytics\Data\DateRangeData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentToneDataController
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
            column: 'tone',
            bucketValues: ['neutral', 'frustrated', 'urgent', 'confused', 'satisfied'],
            startDate: $range->start,
            endDate: $range->end,
        );

        return response()->json([
            'toneByDate' => $result['byDate'],
            'totalTones' => $result['totals'],
        ]);
    }
}

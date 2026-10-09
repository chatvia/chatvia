<?php

namespace App\Http\Controllers\Agent\Analytics\Overview;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Queries\Agent\AgentDailyCountsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daily message counts for the overview chart over the last 7, 14 or 30 days.
 */
class AgentChartDataController
{
    public function __invoke(Request $request, Agent $agent, AgentDailyCountsQuery $daily): JsonResponse
    {
        $days = $request->integer('days', 7);

        // Ensure days is one of the allowed values
        if (! in_array($days, [7, 14, 30])) {
            $days = 7;
        }

        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts
        $messages = collect($daily->replies($agent, $startDate, $endDate))
            ->mapWithKeys(static fn (DailyCountData $day): array => [$day->date => $day->count])
            ->all();

        // Generate all days with message counts, filling missing days with 0
        $chartData = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');

            $chartData[] = [
                'date' => $dateKey,
                'messages' => $messages[$dateKey] ?? 0,
            ];
        }

        return response()->json([
            'data' => $chartData,
            'period' => $days,
        ]);
    }
}

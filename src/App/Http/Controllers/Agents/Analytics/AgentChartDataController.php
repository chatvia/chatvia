<?php

namespace App\Http\Controllers\Agents\Analytics;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentChartDataController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $days = $request->integer('days', 7);

        // Ensure days is one of the allowed values
        if (! in_array($days, [7, 14, 30])) {
            $days = 7;
        }

        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts
        $messages = $agent
            ->messagesByData($startDate, $endDate)
            ->get()
            ->pluck('count', 'date')
            ->toArray();

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

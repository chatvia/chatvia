<?php

namespace Domain\Analytics\Actions;

use Carbon\Carbon;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Build the daily breakdown + range totals for a categorical message dimension
 * (sentiment or tone) from its pre-aggregated daily rollup, instead of scanning
 * chat_messages on every page load.
 *
 * Output is unchanged from the previous live-scan implementation:
 *
 *  - byDate: row-per-day with each bucket value's count (plus 'unspecified' for
 *    user messages that were never analysed)
 *  - totals: each bucket summed across the range, plus 'unspecified' and 'total'
 *
 * 'total' and 'unspecified' are derived from the message rollup's user_messages
 * (total user messages) minus the analysed counts — so a message not yet
 * analysed (or whose tier can't see the result) still shows as unspecified
 * without storing NULL rows. Note: like the other rollups, this counts all user
 * messages regardless of conversation visibility (the previous live query
 * filtered is_visible) — consistent with the message/token rollups.
 */
class AgentMessageDimensionByDateAction
{
    private const TABLES = [
        'sentiment' => 'analytics_agent_sentiment_daily',
        'tone' => 'analytics_agent_tone_daily',
    ];

    /**
     * @param  array<int, string>  $bucketValues  the dimension values that get their own count column
     * @return array{byDate: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function execute(Agent $agent, string $column, array $bucketValues, Carbon $startDate, Carbon $endDate): array
    {
        $table = self::TABLES[$column] ?? throw new InvalidArgumentException("Unsupported dimension column: {$column}");

        $db = DB::connection(config('chatvia.analytics.connection'));
        $start = $startDate->format('Y-m-d');
        $end = $endDate->format('Y-m-d');

        // Analysed counts, indexed [date][value] => count.
        $analyzed = [];
        $rows = $db->table($table)
            ->where('agent_id', $agent->id)
            ->whereBetween('bucket_day', [$start, $end])
            ->groupBy('bucket_day', $column)
            ->selectRaw("bucket_day::text as date, {$column} as bucket, SUM(message_count) as count")
            ->get();
        foreach ($rows as $row) {
            $analyzed[$row->date][$row->bucket] = (int) $row->count;
        }

        // Total user messages per day from the message rollup — drives 'total'
        // and, by subtraction, 'unspecified'.
        $totalByDay = $db->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agent->id)
            ->where('bucket_hour', '>=', $startDate->copy()->startOfDay())
            ->where('bucket_hour', '<', $endDate->copy()->startOfDay()->addDay())
            ->groupByRaw('bucket_hour::date')
            ->selectRaw('bucket_hour::date::text as date, SUM(user_messages) as total')
            ->pluck('total', 'date');

        $byDate = [];
        $totals = array_fill_keys($bucketValues, 0);
        $totals['unspecified'] = 0;
        $totals['total'] = 0;

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $dateStr = $cursor->format('Y-m-d');

            $entry = ['date' => $dateStr];
            $analyzedDay = 0;
            foreach ($bucketValues as $bucket) {
                $count = $analyzed[$dateStr][$bucket] ?? 0;
                $entry[$bucket] = $count;
                $analyzedDay += $count;
                $totals[$bucket] += $count;
            }

            $totalDay = (int) ($totalByDay[$dateStr] ?? 0);
            $unspecifiedDay = max(0, $totalDay - $analyzedDay);

            $entry['unspecified'] = $unspecifiedDay;
            $byDate[] = $entry;

            $totals['unspecified'] += $unspecifiedDay;
            $totals['total'] += $totalDay;
        }

        return ['byDate' => $byDate, 'totals' => $totals];
    }
}

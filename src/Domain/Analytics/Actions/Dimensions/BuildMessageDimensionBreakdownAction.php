<?php

namespace Domain\Analytics\Actions\Dimensions;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\DateRangeData;
use Domain\Analytics\Data\MessageDimensionBreakdownData;
use Domain\Analytics\Enums\MessageDimension;
use Domain\Analytics\Services\AnalyticsDatabase;
use Illuminate\Support\Collection;

/**
 * Builds the daily breakdown + range totals for a message dimension (sentiment
 * or tone) from its pre-aggregated daily rollup, instead of scanning
 * chat_messages on every page load.
 *
 * 'total' and 'unspecified' come from the message rollup's user_messages (all
 * visitor messages) minus the analysed counts — so a message not yet analysed
 * (or whose plan can't see the result) still shows as unspecified without
 * storing NULL rows. Like the other rollups, this counts every visitor message
 * regardless of conversation visibility.
 */
class BuildMessageDimensionBreakdownAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(Agent $agent, MessageDimension $dimension, DateRangeData $range): MessageDimensionBreakdownData
    {
        $analysed = $this->analysedCounts($agent, $dimension, $range);
        $totalByDay = $this->visitorMessagesByDay($agent, $range);
        $buckets = $dimension->buckets();

        $byDate = [];
        $totals = array_fill_keys($buckets, 0) + ['unspecified' => 0, 'total' => 0];

        for ($day = $range->start->copy()->startOfDay(); $day->lte($range->end); $day->addDay()) {
            $date = $day->format('Y-m-d');
            $row = ['date' => $date];
            $analysedToday = 0;

            foreach ($buckets as $bucket) {
                $count = $analysed[$date][$bucket] ?? 0;
                $row[$bucket] = $count;
                $analysedToday += $count;
                $totals[$bucket] += $count;
            }

            $totalToday = (int) ($totalByDay[$date] ?? 0);
            $row['unspecified'] = max(0, $totalToday - $analysedToday);
            $byDate[] = $row;

            $totals['unspecified'] += $row['unspecified'];
            $totals['total'] += $totalToday;
        }

        return new MessageDimensionBreakdownData(byDate: $byDate, totals: $totals);
    }

    /**
     * Analysed message counts, indexed [date][value] => count.
     *
     * @return array<string, array<string, int>>
     */
    private function analysedCounts(Agent $agent, MessageDimension $dimension, DateRangeData $range): array
    {
        $column = $dimension->value;
        $counts = [];

        $rows = $this->analytics->table($dimension->rollupTable())
            ->where('agent_id', $agent->id)
            ->whereBetween('bucket_day', [$range->start->format('Y-m-d'), $range->end->format('Y-m-d')])
            ->groupBy('bucket_day', $column)
            ->selectRaw("bucket_day::text as date, {$column} as bucket, SUM(message_count) as count")
            ->get();

        foreach ($rows as $row) {
            $counts[$row->date][$row->bucket] = (int) $row->count;
        }

        return $counts;
    }

    /**
     * All visitor messages per day, from the daily message rollup.
     *
     * @return Collection<string, int|string>
     */
    private function visitorMessagesByDay(Agent $agent, DateRangeData $range): Collection
    {
        return $this->analytics->table('analytics_agent_daily')
            ->where('agent_id', $agent->id)
            ->whereBetween('bucket_day', [$range->start->format('Y-m-d'), $range->end->format('Y-m-d')])
            ->selectRaw('bucket_day::text as date, user_messages as total')
            ->pluck('total', 'date');
    }
}

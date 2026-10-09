<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\DailyCountData;
use Illuminate\Support\Carbon;

/**
 * An agent's per-day series from the hourly rollup, for the days in an
 * inclusive range: AI replies sent, and conversations started. Days without
 * activity are left out; callers fill the gaps they need.
 */
class AgentDailyCountsQuery extends AgentAnalyticsQuery
{
    /**
     * @return list<DailyCountData>
     */
    public function replies(Agent $agent, Carbon $start, Carbon $end): array
    {
        return $this->perDay($agent, 'assistant_messages', $start, $end);
    }

    /**
     * @return list<DailyCountData>
     */
    public function conversations(Agent $agent, Carbon $start, Carbon $end): array
    {
        return $this->perDay($agent, 'conversations_started', $start, $end);
    }

    /**
     * @return list<DailyCountData>
     */
    private function perDay(Agent $agent, string $column, Carbon $start, Carbon $end): array
    {
        // Half-open range on the raw column — a ::date predicate can't use the index.
        return $this->hourlyRollups($agent)
            ->where('bucket_hour', '>=', $start->copy()->startOfDay())
            ->where('bucket_hour', '<', $end->copy()->startOfDay()->addDay())
            ->selectRaw("bucket_hour::date::text as date, SUM({$column}) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(static fn (object $row): DailyCountData => new DailyCountData((string) $row->date, (int) $row->count))
            ->values()
            ->all();
    }
}

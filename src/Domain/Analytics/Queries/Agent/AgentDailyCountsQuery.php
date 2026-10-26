<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Enums\RollupGrain;
use Domain\Analytics\Services\Period;
use Illuminate\Support\Carbon;

/**
 * An agent's per-day series for the days in an inclusive range: AI replies
 * sent, and conversations started. Read from the hourly rollup up to 92 days
 * and from the daily one beyond ({@see RollupGrain}). Days without activity
 * are left out; callers fill the gaps they need.
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
        $range = $start->format('Y-m-d').'..'.$end->format('Y-m-d');
        $period = new Period($start->toImmutable()->startOfDay(), $end->toImmutable()->endOfDay(), $range);
        $grain = RollupGrain::for($period);

        return $this->cached($agent, $column.'-per-day', $range, fn (): array => $grain->within($this->rollupOf($agent, $grain), $period)
            ->selectRaw("{$grain->day()}::text as date, SUM({$column}) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(static fn (object $row): DailyCountData => new DailyCountData((string) $row->date, (int) $row->count))
            ->values()
            ->all());
    }
}

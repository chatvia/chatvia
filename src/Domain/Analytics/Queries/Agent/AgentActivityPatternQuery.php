<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\HourCountData;
use Domain\Analytics\Data\Agent\Activity\WeekdayCountData;

/**
 * When an agent's conversations start, all time: per hour of the day and per
 * day of the week (UTC). Hours and days without conversations are left out.
 */
class AgentActivityPatternQuery extends AgentAnalyticsQuery
{
    /**
     * @return list<HourCountData>
     */
    public function byHour(Agent $agent): array
    {
        return $this->hourlyRollups($agent)
            ->selectRaw('EXTRACT(HOUR FROM bucket_hour)::int as hour, SUM(conversations_started) as conversation_count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(static fn (object $row): HourCountData => new HourCountData((int) $row->hour, (int) $row->conversation_count))
            ->all();
    }

    /**
     * @return list<WeekdayCountData>
     */
    public function byWeekday(Agent $agent): array
    {
        return $this->hourlyRollups($agent)
            ->selectRaw('EXTRACT(DOW FROM bucket_hour)::int as day_of_week, SUM(conversations_started) as conversation_count')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get()
            ->map(static fn (object $row): WeekdayCountData => new WeekdayCountData((int) $row->day_of_week, (int) $row->conversation_count))
            ->all();
    }
}

<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\ActivityPatternsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\HourCountData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Illuminate\Support\Collection;
use stdClass;

/**
 * When visitors start conversations: per hour of day (UTC) and per weekday,
 * with the peak hour and busiest weekday. On a tie the earlier hour or day
 * wins.
 */
class ActivityPatternsQuery extends AccountAnalyticsQuery
{
    /** Postgres DOW numbering: 0 = Sunday. */
    private const array DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function get(AnalyticsScopeData $scope): ActivityPatternsData
    {
        // `hour` is a keyword in Postgres, so the aliases need an explicit AS.
        $byHour = $this->hourlyRollups($scope)
            ->selectRaw('EXTRACT(HOUR FROM bucket_hour)::int AS hour, SUM(conversations_started) conversations')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $byDay = $this->hourlyRollups($scope)
            ->selectRaw('EXTRACT(DOW FROM bucket_hour)::int AS dow, SUM(conversations_started) conversations')
            ->groupBy('dow')
            ->orderBy('dow')
            ->get();

        $peakHour = $this->busiest($byHour);
        $peakDay = $this->busiest($byDay);

        return new ActivityPatternsData(
            byHour: $byHour->map(static fn (object $row): HourCountData => new HourCountData((int) $row->hour, (int) $row->conversations))->all(),
            byWeekday: $byDay->map(static fn (object $row): LabelCountData => new LabelCountData(self::DAYS[(int) $row->dow] ?? (string) $row->dow, (int) $row->conversations))->all(),
            peakHour: $peakHour ? (int) $peakHour->hour : null,
            busiestWeekday: $peakDay ? (self::DAYS[(int) $peakDay->dow] ?? null) : null,
        );
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     */
    private function busiest(Collection $rows): ?stdClass
    {
        return $rows->sortByDesc('conversations')->first();
    }
}

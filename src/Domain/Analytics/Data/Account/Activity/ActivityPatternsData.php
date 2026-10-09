<?php

namespace Domain\Analytics\Data\Account\Activity;

use Domain\Analytics\Data\Account\HourCountData;
use Domain\Analytics\Data\Account\LabelCountData;

/**
 * When visitors start conversations: per hour of day (UTC) and per weekday,
 * with the peak hour and busiest weekday (null when there is no activity).
 */
final readonly class ActivityPatternsData
{
    /**
     * @param  list<HourCountData>  $byHour  Hours with activity, in hour order.
     * @param  list<LabelCountData>  $byWeekday  Weekdays with activity, Sunday first.
     */
    public function __construct(
        public array $byHour,
        public array $byWeekday,
        public ?int $peakHour,
        public ?string $busiestWeekday,
    ) {}
}

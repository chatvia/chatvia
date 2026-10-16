<?php

namespace Domain\Analytics\Data\Agent;

use Domain\Analytics\Data\Account\DailyCountData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The overview message chart: one point per day over the chosen period, days
 * without messages filled with zero.
 */
#[TypeScript]
class MessageChartResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, DailyMessagePointData> */
        #[DataCollectionOf(DailyMessagePointData::class)]
        public DataCollection $data,
        public int $period,
    ) {}

    /**
     * @param  list<DailyCountData>  $counts
     */
    public static function fromDailyCounts(array $counts, int $days): self
    {
        $byDate = [];
        foreach ($counts as $day) {
            $byDate[$day->date] = $day->count;
        }

        $points = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');

            $points[] = new DailyMessagePointData(date: $date, messages: $byDate[$date] ?? 0);
        }

        return new self(
            data: new DataCollection(DailyMessagePointData::class, $points),
            period: $days,
        );
    }
}

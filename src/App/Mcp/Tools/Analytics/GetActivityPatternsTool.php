<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\HourCountData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Queries\Account\Activity\ActivityPatternsQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-activity-patterns: peak hour and busiest weekday, with an hourly chart.
 */
#[Description(<<<'TXT'
When visitors are active, for a period: conversations by hour of day (0–23, UTC)
and by day of week, with the peak hour and busiest weekday called out. Answers
"when are people most active?", "what's my peak hour?", "which weekday is
busiest?". The hourly and weekday series make a good bar or heatmap chart.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetActivityPatternsTool extends AnalyticsTool
{
    public function __construct(private readonly ActivityPatternsQuery $patterns) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->patterns->get($scope);
        $period = $scope->period;

        $summary = $data->peakHour === null
            ? sprintf('No activity data %s yet.', $period->label)
            : sprintf(
                'Busiest %s around %02d:00 UTC%s.',
                $period->label,
                $data->peakHour,
                $data->busiestWeekday !== null ? ', most on '.$data->busiestWeekday : '',
            );

        $hours = array_map(static fn (HourCountData $hour): array => ['hour' => $hour->hour, 'conversations' => $hour->count], $data->byHour);

        $structured = [
            'period' => $this->periodPayload($period),
            'peak_hour_utc' => $data->peakHour,
            'busiest_weekday' => $data->busiestWeekday,
            'by_hour' => $hours,
            'by_weekday' => array_map(static fn (LabelCountData $day): array => ['day' => $day->label, 'conversations' => $day->count], $data->byWeekday),
        ];

        if (count($hours) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_map(static fn (array $hour): string => sprintf('%02d:00', $hour['hour']), $hours),
                [['name' => 'Conversations', 'data' => array_column($hours, 'conversations')]],
                'Activity by hour (UTC) — '.$period->label,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }
}

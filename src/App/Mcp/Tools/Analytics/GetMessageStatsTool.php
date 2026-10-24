<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Queries\Account\Activity\MessageStatsQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-message-stats: message volume, the trend, and a per-day line chart.
 */
#[Description(<<<'TXT'
Message volume for a period: how many messages came in (from visitors) vs went
out (AI replies), the combined total, the change vs the previous period, the
average messages per conversation, and a per-day series. Answers "how many
messages this month?", "incoming vs outgoing?", "are we up or down vs last
month?". The per-day series is ideal to hand to a chart tool.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetMessageStatsTool extends AnalyticsTool
{
    public function __construct(private readonly MessageStatsQuery $messages) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->messages->get($scope);
        $period = $scope->period;

        $trend = $data->changePct === null ? '' : sprintf(' %s %s%% vs %s.', $data->changePct >= 0 ? 'up' : 'down', abs($data->changePct), 'the prior period');

        $summary = sprintf(
            '%s: %s messages total — %s incoming, %s AI replies.%s',
            Str::ucfirst($period->label),
            number_format($data->total()),
            number_format($data->incoming),
            number_format($data->outgoing),
            $trend,
        );

        $daily = array_map(static fn (DailyCountData $day): array => ['date' => $day->date, 'messages' => $day->count], $data->daily);

        $structured = [
            'period' => $this->periodPayload($period),
            'incoming_messages' => $data->incoming,
            'ai_messages' => $data->outgoing,
            'total_messages' => $data->total(),
            'previous_total' => $data->previousTotal,
            'change_pct' => $data->changePct,
            'avg_messages_per_conversation' => $data->avgPerConversation,
            'daily' => $daily,
        ];

        // Auto-render a trend line so the owner sees the shape without asking.
        if (count($daily) > 1) {
            $structured['echarts_option'] = AnalyticsChart::line(
                array_column($daily, 'date'),
                [['name' => 'Messages', 'data' => array_column($daily, 'messages')]],
                'Messages per day — '.$period->label,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }
}

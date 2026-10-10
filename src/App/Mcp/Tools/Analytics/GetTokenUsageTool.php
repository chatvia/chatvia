<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Queries\Account\Usage\TokenUsageQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-token-usage: tokens consumed, with a per-day line chart.
 */
#[Description(<<<'TXT'
Token consumption for a period: total tokens used, the prompt vs completion
split, the average tokens per AI reply, and a per-day series. Answers "how many
tokens did I consume this month?", "what's my prompt vs completion split?".
Tokens are reported for analytics only; message limits count replies, not
tokens.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetTokenUsageTool extends AnalyticsTool
{
    public function __construct(private readonly TokenUsageQuery $tokens) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->tokens->get($scope);
        $period = $scope->period;

        $summary = $data->total === 0
            ? sprintf('No tokens consumed %s.', $period->label)
            : sprintf(
                '%s: %s tokens consumed (%s prompt, %s completion), ~%s per AI reply.',
                ucfirst($period->label),
                number_format($data->total),
                number_format($data->prompt),
                number_format($data->completion),
                number_format($data->avgPerReply),
            );

        $daily = array_map(static fn (DailyCountData $day): array => ['date' => $day->date, 'tokens' => $day->count], $data->daily);

        $structured = [
            'period' => $this->periodPayload($period),
            'total_tokens' => $data->total,
            'prompt_tokens' => $data->prompt,
            'completion_tokens' => $data->completion,
            'avg_tokens_per_reply' => $data->avgPerReply,
            'daily' => $daily,
        ];

        if (count($daily) > 1) {
            $structured['echarts_option'] = AnalyticsChart::line(
                array_column($daily, 'date'),
                [['name' => 'Tokens', 'data' => array_column($daily, 'tokens')]],
                'Tokens per day — '.$period->label,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }
}

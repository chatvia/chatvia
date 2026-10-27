<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\Outcomes\SatisfactionStatsQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-satisfaction-stats: feedback, sentiment, unanswered rate, confidence.
 */
#[Description(<<<'TXT'
Quality and satisfaction signals for a period: thumbs up vs down (and the
positive rate), the sentiment split of messages (positive / neutral / negative),
how often the AI could not find an answer, and the average answer confidence.
Answers "are people happy?", "how's sentiment?", "how often does the AI fail to
answer?". Use this to spot knowledge gaps.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetSatisfactionStatsTool extends AnalyticsTool
{
    public function __construct(private readonly SatisfactionStatsQuery $satisfaction) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->satisfaction->get($scope);
        $period = $scope->period;
        $sentiment = $data->sentiment;

        $summary = sprintf(
            '%s: %s👍 / %s👎 (%s%% positive). AI could not answer %s%% of replies.',
            Str::ucfirst($period->label),
            Number::format($data->feedback->positive),
            Number::format($data->feedback->negative),
            $data->positiveRatePct,
            $data->unansweredRatePct,
        );

        $structured = [
            'period' => $this->periodPayload($period),
            'positive_feedback' => $data->feedback->positive,
            'negative_feedback' => $data->feedback->negative,
            'positive_rate_pct' => $data->positiveRatePct,
            'sentiment' => [
                'positive' => $sentiment->positive,
                'neutral' => $sentiment->neutral,
                'negative' => $sentiment->negative,
            ],
            'ai_replies' => $data->aiReplies,
            'unanswered' => $data->unanswered,
            'unanswered_rate_pct' => $data->unansweredRatePct,
            'avg_confidence' => $data->avgConfidence,
        ];

        if ($sentiment->total() > 0) {
            $structured['echarts_option'] = AnalyticsChart::pie([
                ['name' => 'Positive', 'value' => $sentiment->positive],
                ['name' => 'Neutral', 'value' => $sentiment->neutral],
                ['name' => 'Negative', 'value' => $sentiment->negative],
            ], 'Sentiment — '.$period->label, donut: true);
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }
}

<?php

namespace Domain\Analytics\Queries\Account\Usage;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Data\Account\Usage\TokenUsageData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * Tokens consumed: total, prompt vs completion, per AI reply, and per day.
 * Tokens live on each message event, by exact time.
 */
class TokenUsageQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): TokenUsageData
    {
        $totals = $this->messageEvents($scope)
            ->selectRaw("COALESCE(SUM(total_tokens),0) total, COALESCE(SUM(prompt_tokens),0) prompt, COALESCE(SUM(completion_tokens),0) completion, COUNT(*) FILTER (WHERE role = 'assistant' AND total_tokens > 0) replies")
            ->first();

        $total = (int) $totals->total;
        $replies = (int) $totals->replies;

        $daily = $this->messageEvents($scope)
            ->selectRaw('occurred_at::date date, SUM(total_tokens) tokens')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(static fn (object $row): DailyCountData => new DailyCountData((string) $row->date, (int) $row->tokens))
            ->all();

        return new TokenUsageData(
            total: $total,
            prompt: (int) $totals->prompt,
            completion: (int) $totals->completion,
            avgPerReply: $replies > 0 ? (int) round($total / $replies) : 0,
            daily: $daily,
        );
    }
}

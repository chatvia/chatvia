<?php

namespace Domain\Analytics\Queries\Account\Usage;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Data\Account\Usage\TokenUsageData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsRollups;

/**
 * Tokens consumed: total, prompt vs completion, per AI reply, and per day,
 * from the hourly or daily rollup. "Per reply" counts the replies of the hours
 * (or days) that used tokens, so those with only replies that called no model
 * (a handoff notice, an error) do not lower it.
 */
class TokenUsageQuery extends AccountAnalyticsQuery
{
    use ReadsRollups;

    public function get(AnalyticsScopeData $scope): TokenUsageData
    {
        $totals = $this->rollup($scope)
            ->selectRaw('COALESCE(SUM(total_tokens),0) total, COALESCE(SUM(prompt_tokens),0) prompt, COALESCE(SUM(completion_tokens),0) completion, COALESCE(SUM(assistant_messages) FILTER (WHERE total_tokens > 0),0) replies')
            ->first();

        $total = (int) $totals->total;
        $replies = (int) $totals->replies;

        $daily = $this->rollup($scope)
            ->selectRaw($this->grain($scope)->day().' date, SUM(total_tokens) tokens')
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

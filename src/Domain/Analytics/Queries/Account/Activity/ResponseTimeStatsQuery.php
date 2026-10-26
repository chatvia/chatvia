<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\ResponseTimeStatsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsRollups;
use Domain\Analytics\Services\ResponseTimeHistogram;

/**
 * AI reply latency: average, median, p95 and slowest reply, plus the average
 * time to the first reply of a conversation. All of it but the first-reply
 * time comes from the hourly or daily rollup: the median and p95 from the
 * rows' reply-time histograms summed bucket by bucket
 * ({@see ResponseTimeHistogram}, within about 15%), the slowest reply from
 * each row's slowest. The first-reply time comes from the per-conversation
 * metrics.
 */
class ResponseTimeStatsQuery extends AccountAnalyticsQuery
{
    use ReadsRollups;

    public function get(AnalyticsScopeData $scope): ResponseTimeStatsData
    {
        $measured = $this->rollup($scope)
            ->selectRaw('COALESCE(SUM(response_ms_count),0) n, SUM(response_ms_sum)::numeric / NULLIF(SUM(response_ms_count),0) avg_ms, MAX(response_ms_max) FILTER (WHERE response_ms_count > 0) max_ms')
            ->first();

        $maxMs = $measured->max_ms !== null ? (int) $measured->max_ms : null;
        $histogram = $this->histogram($scope);

        return new ResponseTimeStatsData(
            repliesMeasured: (int) $measured->n,
            avgMs: $this->roundOrNull($measured->avg_ms, 0),
            p50Ms: $this->roundOrNull(ResponseTimeHistogram::percentile($histogram, 0.5, $maxMs), 0),
            p95Ms: $this->roundOrNull(ResponseTimeHistogram::percentile($histogram, 0.95, $maxMs), 0),
            maxMs: $maxMs,
            avgFirstResponseSeconds: $this->roundOrNull($this->averageFirstResponseSeconds($scope), 1),
        );
    }

    /**
     * The scope's reply-time histogram: the rows' counts summed by bucket.
     *
     * @return array<int, int> Counts by bucket, from 0.
     */
    private function histogram(AnalyticsScopeData $scope): array
    {
        return $this->rollup($scope)
            ->where('response_ms_count', '>', 0)
            ->crossJoin($this->analytics()->raw('unnest(response_ms_hist) WITH ORDINALITY AS h(count, bucket)'))
            ->groupBy('h.bucket')
            ->selectRaw('h.bucket - 1 AS bucket, SUM(h.count) AS replies')
            ->pluck('replies', 'bucket')
            ->map(static fn (mixed $replies): int => (int) $replies)
            ->all();
    }

    private function averageFirstResponseSeconds(AnalyticsScopeData $scope): int|float|string|null
    {
        return $this->conversationMetrics($scope)
            ->whereColumn('first_assistant_at', '>', 'first_user_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_assistant_at - first_user_at))) avg_first')
            ->first()
            ?->avg_first;
    }
}

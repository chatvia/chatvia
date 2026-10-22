<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\ResponseTimeStatsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsHourlyRollups;
use Domain\Conversation\Enums\MessageRole;

/**
 * AI reply latency: average, median, p95 and slowest reply, plus the average
 * time to the first reply of a conversation. The count and the average come
 * from the hourly rollup and the first-reply time from the per-conversation
 * metrics. The median, p95 and slowest reply need single messages, so they
 * read the raw events the prune still keeps. That read uses only columns the
 * (workspace_id, event_type, occurred_at) index carries, so Postgres can
 * answer it from the index alone.
 */
class ResponseTimeStatsQuery extends AccountAnalyticsQuery
{
    use ReadsHourlyRollups;

    public function get(AnalyticsScopeData $scope): ResponseTimeStatsData
    {
        $measured = $this->rollupHours($scope)
            ->selectRaw('COALESCE(SUM(response_ms_count),0) n, SUM(response_ms_sum)::numeric / NULLIF(SUM(response_ms_count),0) avg_ms')
            ->first();

        $rawPeriod = $this->rawEventPeriod($scope);

        $distribution = $this->messageEvents($scope, $rawPeriod)
            ->where('role', MessageRole::Assistant->value)
            ->whereNotNull('response_time_ms')
            ->selectRaw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY response_time_ms) p50_ms, PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) p95_ms, MAX(response_time_ms) max_ms')
            ->first();

        return new ResponseTimeStatsData(
            repliesMeasured: (int) $measured->n,
            avgMs: $this->roundOrNull($measured->avg_ms, 0),
            p50Ms: $this->roundOrNull($distribution->p50_ms, 0),
            p95Ms: $this->roundOrNull($distribution->p95_ms, 0),
            maxMs: $distribution->max_ms !== null ? (int) $distribution->max_ms : null,
            avgFirstResponseSeconds: $this->roundOrNull($this->averageFirstResponseSeconds($scope), 1),
        );
    }

    /**
     * Per conversation started in the window: seconds from its first visitor
     * message to its first AI reply after it, averaged. Read from the
     * per-conversation metrics, which keep both times as messages arrive.
     */
    private function averageFirstResponseSeconds(AnalyticsScopeData $scope): int|float|string|null
    {
        return $this->conversationMetrics($scope)
            ->whereColumn('first_assistant_at', '>', 'first_user_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_assistant_at - first_user_at))) avg_first')
            ->first()
            ?->avg_first;
    }
}

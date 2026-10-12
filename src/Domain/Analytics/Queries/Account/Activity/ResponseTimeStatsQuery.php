<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\ResponseTimeStatsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsHourlyRollups;
use Domain\Analytics\Services\Period;

/**
 * AI reply latency: average, median, p95 and slowest reply, plus the average
 * time to the first reply of a conversation. The count and the average come
 * from the hourly rollup; the median, p95, slowest reply and first-reply time
 * need single messages, so they read the raw events the prune still keeps.
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
            ->where('role', 'assistant')
            ->whereNotNull('response_time_ms')
            ->selectRaw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY response_time_ms) p50_ms, PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) p95_ms, MAX(response_time_ms) max_ms')
            ->first();

        return new ResponseTimeStatsData(
            repliesMeasured: (int) $measured->n,
            avgMs: $this->roundOrNull($measured->avg_ms, 0),
            p50Ms: $this->roundOrNull($distribution->p50_ms, 0),
            p95Ms: $this->roundOrNull($distribution->p95_ms, 0),
            maxMs: $distribution->max_ms !== null ? (int) $distribution->max_ms : null,
            avgFirstResponseSeconds: $this->roundOrNull($this->averageFirstResponseSeconds($scope, $rawPeriod), 1),
        );
    }

    /**
     * Per conversation: seconds from the first visitor message to the first
     * AI reply after it, averaged.
     */
    private function averageFirstResponseSeconds(AnalyticsScopeData $scope, Period $period): int|float|string|null
    {
        $agentFilter = $scope->agentId !== null ? ' AND agent_id = ?' : '';
        $firstMessagePerConversation = "SELECT conversation_external_id, MIN(occurred_at) occurred_at FROM analytics_events
                   WHERE workspace_id = ? AND event_type = ? AND role = ? AND occurred_at BETWEEN ? AND ?{$agentFilter}
                   GROUP BY conversation_external_id";

        $bindings = fn (string $role): array => [
            $scope->workspace->id,
            AnalyticsEventType::MessageSent->value,
            $role,
            $period->start->toDateTimeString(),
            $period->end->toDateTimeString(),
            ...($scope->agentId !== null ? [$scope->agentId] : []),
        ];

        $row = $this->analytics()->selectOne(
            "SELECT AVG(EXTRACT(EPOCH FROM (a.occurred_at - u.occurred_at))) avg_first
             FROM ({$firstMessagePerConversation}) u
             JOIN ({$firstMessagePerConversation}) a ON a.conversation_external_id = u.conversation_external_id
             WHERE a.occurred_at > u.occurred_at",
            [...$bindings('user'), ...$bindings('assistant')],
        );

        return $row->avg_first ?? null;
    }
}

<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\ResponseTimeStatsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * AI reply latency: average, median, p95 and slowest reply, plus the average
 * time to the first reply of a conversation.
 */
class ResponseTimeStatsQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): ResponseTimeStatsData
    {
        $distribution = $this->messageEvents($scope)
            ->where('role', 'assistant')
            ->whereNotNull('response_time_ms')
            ->selectRaw('AVG(response_time_ms) avg_ms, PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY response_time_ms) p50_ms, PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) p95_ms, MAX(response_time_ms) max_ms, COUNT(*) n')
            ->first();

        return new ResponseTimeStatsData(
            repliesMeasured: (int) $distribution->n,
            avgMs: $this->roundOrNull($distribution->avg_ms, 0),
            p50Ms: $this->roundOrNull($distribution->p50_ms, 0),
            p95Ms: $this->roundOrNull($distribution->p95_ms, 0),
            maxMs: $distribution->max_ms !== null ? (int) $distribution->max_ms : null,
            avgFirstResponseSeconds: $this->roundOrNull($this->averageFirstResponseSeconds($scope), 1),
        );
    }

    /**
     * Per conversation: seconds from the first visitor message to the first
     * AI reply after it, averaged.
     */
    private function averageFirstResponseSeconds(AnalyticsScopeData $scope): int|float|string|null
    {
        $agentFilter = $scope->agentId !== null ? ' AND agent_id = ?' : '';
        $firstMessagePerConversation = "SELECT conversation_external_id, MIN(occurred_at) occurred_at FROM analytics_events
                   WHERE workspace_id = ? AND event_type = ? AND role = ? AND occurred_at BETWEEN ? AND ?{$agentFilter}
                   GROUP BY conversation_external_id";

        $bindings = fn (string $role): array => [
            $scope->workspace->id,
            AnalyticsEventType::MessageSent->value,
            $role,
            $scope->period->start->toDateTimeString(),
            $scope->period->end->toDateTimeString(),
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

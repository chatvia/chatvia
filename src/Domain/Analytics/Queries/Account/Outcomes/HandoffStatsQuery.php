<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Account\Outcomes;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Outcomes\HandoffStatsData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * Escalations from the AI to a human in the window, the handoff rate, and the
 * handoffs waiting for a human right now (whenever they started).
 */
class HandoffStatsQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): HandoffStatsData
    {
        $window = [$scope->period->start, $scope->period->end];

        $conversations = (int) $this->liveConversations($scope)->whereBetween('c.created_at', $window)->count();
        $handoffs = (int) $this->liveConversations($scope)->whereBetween('c.created_at', $window)->whereNotNull('c.handoff_at')->count();

        return new HandoffStatsData(
            conversations: $conversations,
            handoffs: $handoffs,
            handoffRatePct: $this->pct($handoffs, $conversations),
            pendingNow: (int) $this->liveConversations($scope)->where('c.handoff_status', 'pending')->count(),
        );
    }
}

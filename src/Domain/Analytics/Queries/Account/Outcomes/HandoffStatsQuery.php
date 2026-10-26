<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Account\Outcomes;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Outcomes\HandoffStatsData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Conversation\Enums\HandoffStatus;

/**
 * Escalations from the AI to a human in the window, the handoff rate, and the
 * handoffs waiting for a human right now (whenever they started).
 */
class HandoffStatsQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): HandoffStatsData
    {
        // One pass over the window's conversations for both counts.
        $counts = $this->liveConversations($scope)
            ->whereBetween('c.created_at', [$scope->period->start, $scope->period->end])
            ->selectRaw('COUNT(*) conversations, COUNT(*) FILTER (WHERE c.handoff_at IS NOT NULL) handoffs')
            ->first();

        $conversations = (int) $counts->conversations;
        $handoffs = (int) $counts->handoffs;

        return new HandoffStatsData(
            conversations: $conversations,
            handoffs: $handoffs,
            handoffRatePct: $this->pct($handoffs, $conversations),
            pendingNow: (int) $this->liveConversations($scope)->where('c.handoff_status', HandoffStatus::Pending->value)->count(),
        );
    }
}

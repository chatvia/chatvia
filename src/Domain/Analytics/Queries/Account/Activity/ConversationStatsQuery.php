<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\ConversationStatsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * Conversation-level numbers: how many started, average length and duration,
 * and the busiest day.
 */
class ConversationStatsQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): ConversationStatsData
    {
        $metrics = $this->conversationMetrics($scope)
            ->selectRaw('AVG(message_count) avg_messages, AVG(NULLIF(duration_seconds, 0)) avg_duration')
            ->first();

        $busiest = $this->hourlyRollups($scope)
            ->selectRaw('bucket_hour::date date, SUM(conversations_started) c')
            ->groupBy('date')
            ->orderByRaw('SUM(conversations_started) DESC')
            ->limit(1)
            ->first();

        return new ConversationStatsData(
            conversations: (int) $this->hourlyRollups($scope)->sum('conversations_started'),
            avgMessagesPerConversation: $this->roundOrNull($metrics->avg_messages, 1),
            avgDurationSeconds: $this->roundOrNull($metrics->avg_duration, 0),
            busiestDay: $busiest ? new DailyCountData((string) $busiest->date, (int) $busiest->c) : null,
        );
    }
}

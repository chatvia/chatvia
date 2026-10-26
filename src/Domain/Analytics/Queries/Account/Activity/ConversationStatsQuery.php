<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\ConversationStatsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsRollups;

/**
 * Conversation-level numbers: how many started, average length and duration,
 * and the busiest day. The starts come from one pass over the rollup by day
 * (the total is the sum of the days), the length and duration from the
 * per-conversation metrics.
 */
class ConversationStatsQuery extends AccountAnalyticsQuery
{
    use ReadsRollups;

    public function get(AnalyticsScopeData $scope): ConversationStatsData
    {
        $metrics = $this->conversationMetrics($scope)
            ->selectRaw('AVG(message_count) avg_messages, AVG(NULLIF(duration_seconds, 0)) avg_duration')
            ->first();

        $days = $this->rollup($scope)
            ->selectRaw($this->grain($scope)->day().' date, SUM(conversations_started) c')
            ->groupBy('date')
            ->get();

        $busiest = $days->where('c', '>', 0)->sortByDesc('c')->first();

        return new ConversationStatsData(
            conversations: (int) $days->sum('c'),
            avgMessagesPerConversation: $this->roundOrNull($metrics->avg_messages, 1),
            avgDurationSeconds: $this->roundOrNull($metrics->avg_duration, 0),
            busiestDay: $busiest !== null ? new DailyCountData((string) $busiest->date, (int) $busiest->c) : null,
        );
    }
}

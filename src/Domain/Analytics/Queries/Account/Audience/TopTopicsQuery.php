<?php

namespace Domain\Analytics\Queries\Account\Audience;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Illuminate\Support\Facades\DB;

/**
 * The topics visitors talk about most, ranked by the number of conversations
 * each appears in.
 */
class TopTopicsQuery extends AccountAnalyticsQuery
{
    /**
     * @return list<LabelCountData> Topic name and conversation count, most first.
     */
    public function get(AnalyticsScopeData $scope, int $limit): array
    {
        $rows = $this->dailyRollup($scope, 'analytics_agent_topic_daily')
            ->groupBy('topic_id')
            ->selectRaw('topic_id, SUM(conversation_count) conversations')
            ->orderByDesc('conversations')
            ->limit($limit)
            ->get();

        // Topic labels live on the main DB; resolve in PHP so this works even
        // when analytics runs on a separate connection.
        $names = DB::table('topics')->whereIn('id', $rows->pluck('topic_id')->all())->pluck('topic', 'id');

        return $rows->map(static fn (object $row): LabelCountData => new LabelCountData(
            label: $names[$row->topic_id] ?? 'Topic #'.$row->topic_id,
            count: (int) $row->conversations,
        ))->all();
    }
}

<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\AgentActivityData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Illuminate\Support\Facades\DB;

/**
 * Traffic per agent, busiest first. Always the whole account: narrowing to
 * one agent would defeat the comparison, so the scope's agent is ignored.
 */
class AgentBreakdownQuery extends AccountAnalyticsQuery
{
    /**
     * @return list<AgentActivityData>
     */
    public function get(AnalyticsScopeData $scope): array
    {
        $rows = $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $scope->workspace->id)
            ->whereBetween('bucket_hour', [$scope->period->start->copy()->startOfHour(), $scope->period->end])
            ->groupBy('agent_id')
            ->selectRaw('agent_id, SUM(user_messages + assistant_messages) messages, SUM(conversations_started) conversations')
            ->orderByRaw('SUM(user_messages + assistant_messages) DESC')
            ->get();

        // Agent names live on the main DB; resolve them in PHP so this works
        // even when analytics runs on a separate connection.
        $names = DB::table('agents')
            ->whereIn('id', $rows->pluck('agent_id')->all())
            ->pluck('name', 'id');

        return $rows->map(static fn (object $row): AgentActivityData => new AgentActivityData(
            agentId: (int) $row->agent_id,
            name: $names[$row->agent_id] ?? 'Agent #'.$row->agent_id,
            messages: (int) $row->messages,
            conversations: (int) $row->conversations,
        ))->all();
    }
}

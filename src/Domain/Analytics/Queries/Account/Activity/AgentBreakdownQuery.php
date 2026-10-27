<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\AgentActivityData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsRollups;
use Illuminate\Support\Facades\DB;

/**
 * Traffic per agent, busiest first. Always the whole account: narrowing to
 * one agent would defeat the comparison, so the scope's agent is ignored.
 */
class AgentBreakdownQuery extends AccountAnalyticsQuery
{
    use ReadsRollups;

    /**
     * @return list<AgentActivityData>
     */
    public function get(AnalyticsScopeData $scope): array
    {
        $workspace = new AnalyticsScopeData(workspace: $scope->workspace, period: $scope->period, agentId: null, agentIds: $scope->agentIds);

        $rows = $this->rollup($workspace)
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
            name: $names[$row->agent_id] ?? "Agent #{$row->agent_id}",
            messages: (int) $row->messages,
            conversations: (int) $row->conversations,
        ))->all();
    }
}

<?php

namespace Domain\Analytics\Queries\Agent;

/**
 * All-time message counts (visitor messages + AI replies) for a set of
 * agents, in one grouped query over the hourly rollup — so the totals survive
 * retention pruning of the raw chat rows.
 */
class AgentMessageTotalsQuery extends AgentAnalyticsQuery
{
    /**
     * @param  list<int>  $agentIds
     * @return array<int, int> agent id → messages; agents without any are left out
     */
    public function get(array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }

        return $this->analytics()
            ->table('analytics_agent_hourly_rollups')
            ->whereIn('agent_id', $agentIds)
            ->groupBy('agent_id')
            ->selectRaw('agent_id, SUM(user_messages + assistant_messages) AS total_messages')
            ->pluck('total_messages', 'agent_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }
}

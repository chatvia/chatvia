<?php

namespace Domain\Analytics\Queries\Agent;

/**
 * All-time message counts (visitor messages + AI replies) for a set of a
 * workspace's agents, in one grouped query over the hourly rollup — so the
 * totals survive retention pruning of the raw chat rows. Cached briefly per
 * set of agents: the agents list asks on every visit.
 */
class AgentMessageTotalsQuery extends AgentAnalyticsQuery
{
    /**
     * @param  list<int>  $agentIds
     * @return array<int, int> agent id → messages; agents without any are left out
     */
    public function get(int $workspaceId, array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }

        sort($agentIds);

        return $this->cachedForWorkspace($workspaceId, 'agent-messages:'.md5(implode(',', $agentIds)), 'lifetime', fn (): array => $this->compute($agentIds));
    }

    /**
     * @param  list<int>  $agentIds
     * @return array<int, int>
     */
    private function compute(array $agentIds): array
    {
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

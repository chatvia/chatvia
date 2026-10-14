<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Carbon\CarbonImmutable;
use Domain\Analytics\Data\Platform\AgentActivityData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Illuminate\Database\Query\Builder;

/**
 * Traffic of the given agents over the window, and the last hour each had
 * any traffic, for the admin panel's list of a workspace's agents. The
 * caller passes the agents of one page of the list, so both reads stay on
 * the rollup's (agent_id, …) primary key.
 */
class AgentActivityQuery extends PlatformAnalyticsQuery
{
    /**
     * Every given agent is in the result, with zeros when it had no traffic.
     *
     * @param  list<int>  $agentIds
     * @return array<int, AgentActivityData> keyed by agent id
     */
    public function get(PlatformScopeData $scope, array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }

        $traffic = $this->rollups($scope)
            ->whereIn('agent_id', $agentIds)
            ->groupBy('agent_id')
            ->selectRaw('agent_id, SUM(user_messages + assistant_messages) messages, SUM(conversations_started) conversations')
            ->get()
            ->keyBy('agent_id');

        $lastActive = $this->analytics()->table('analytics_agent_hourly_rollups')
            ->when(! $scope->isWholePlatform(), fn (Builder $query): Builder => $query->where('workspace_id', $scope->workspaceId))
            ->whereIn('agent_id', $agentIds)
            ->groupBy('agent_id')
            ->selectRaw('agent_id, MAX(bucket_hour) last_active_at')
            ->pluck('last_active_at', 'agent_id');

        return collect($agentIds)
            ->mapWithKeys(fn (int $agentId): array => [$agentId => new AgentActivityData(
                agentId: $agentId,
                messages: (int) ($traffic->get($agentId)->messages ?? 0),
                conversations: (int) ($traffic->get($agentId)->conversations ?? 0),
                lastActiveAt: isset($lastActive[$agentId]) ? CarbonImmutable::parse($lastActive[$agentId]) : null,
            )])
            ->all();
    }
}

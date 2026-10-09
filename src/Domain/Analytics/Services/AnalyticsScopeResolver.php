<?php

namespace Domain\Analytics\Services;

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;

/**
 * Turns an account-analytics filter into a concrete scope for one workspace:
 * the time window ({@see AnalyticsPeriodResolver}) and the agents to count.
 * The agent is looked up among the workspace's OWN agents only, so another
 * workspace's agent id yields an empty scope, never its numbers.
 */
class AnalyticsScopeResolver
{
    public function __construct(private readonly AnalyticsPeriodResolver $periods) {}

    public function resolve(User $workspace, AnalyticsFilterData $filter): AnalyticsScopeData
    {
        $period = $this->periods->resolve($workspace, $filter);

        if ($filter->agentExternalId === null) {
            return new AnalyticsScopeData(
                workspace: $workspace,
                period: $period,
                agentId: null,
                agentIds: $workspace->agents()->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            );
        }

        $agentId = $workspace->agents()->where('external_id', $filter->agentExternalId)->value('id');
        $agentId = $agentId === null ? AnalyticsScopeData::UNKNOWN_AGENT : (int) $agentId;

        return new AnalyticsScopeData(
            workspace: $workspace,
            period: $period,
            agentId: $agentId,
            agentIds: [$agentId],
        );
    }
}

<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Domain\Analytics\Data\Platform\PlatformScopeData;
use Illuminate\Database\Query\Builder;

/**
 * How many times agents called a tool in the window: webhook functions and
 * MCP tools together, across the install or within the scoped workspace.
 * Both are counted per agent and day, so the window is read in whole days.
 */
class ToolCallsQuery extends PlatformAnalyticsQuery
{
    public function count(PlatformScopeData $scope): int
    {
        return $this->invocations('analytics_agent_function_daily', $scope)
            + $this->invocations('analytics_agent_mcp_tool_daily', $scope);
    }

    private function invocations(string $table, PlatformScopeData $scope): int
    {
        return (int) $this->analytics()->table($table)
            ->when(! $scope->isWholePlatform(), fn (Builder $query): Builder => $query->where('workspace_id', $scope->workspaceId))
            ->whereBetween('bucket_day', [$scope->period->start->toDateString(), $scope->period->end->toDateString()])
            ->sum('invocation_count');
    }
}

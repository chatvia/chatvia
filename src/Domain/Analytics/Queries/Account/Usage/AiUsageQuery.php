<?php

namespace Domain\Analytics\Queries\Account\Usage;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Data\Account\Usage\AiUsageData;
use Domain\Analytics\Data\Account\Usage\McpToolUsageData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * What the AI relied on: the most-called custom functions (webhook tools),
 * the most-used MCP connector tools and the most-retrieved knowledge sources.
 */
class AiUsageQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope, int $limit): AiUsageData
    {
        return new AiUsageData(
            topFunctions: $this->topFunctions($scope, $limit),
            topMcpTools: $this->topMcpTools($scope, $limit),
            topKnowledgeSources: $this->topSources($scope, $limit),
        );
    }

    /**
     * @return list<LabelCountData>
     */
    private function topFunctions(AnalyticsScopeData $scope, int $limit): array
    {
        return $this->dailyRollup($scope, 'analytics_agent_function_daily')
            ->groupBy('function_id')
            ->selectRaw('MAX(function_name) name, SUM(invocation_count) calls')
            ->orderByRaw('SUM(invocation_count) DESC')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): LabelCountData => new LabelCountData((string) $row->name, (int) $row->calls))
            ->all();
    }

    /**
     * @return list<McpToolUsageData>
     */
    private function topMcpTools(AnalyticsScopeData $scope, int $limit): array
    {
        return $this->dailyRollup($scope, 'analytics_agent_mcp_tool_daily')
            ->groupBy('server_slug', 'tool_name')
            ->selectRaw('tool_name, MAX(server_name) server, SUM(invocation_count) calls')
            ->orderByRaw('SUM(invocation_count) DESC')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): McpToolUsageData => new McpToolUsageData((string) $row->tool_name, (string) $row->server, (int) $row->calls))
            ->all();
    }

    /**
     * @return list<LabelCountData>
     */
    private function topSources(AnalyticsScopeData $scope, int $limit): array
    {
        return $this->dailyRollup($scope, 'analytics_agent_source_daily')
            ->groupBy('sourceable_type', 'sourceable_id')
            ->selectRaw('MAX(source_name) name, SUM(retrieval_count) retrievals')
            ->orderByRaw('SUM(retrieval_count) DESC')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): LabelCountData => new LabelCountData((string) ($row->name ?: 'Untitled'), (int) $row->retrievals))
            ->all();
    }
}

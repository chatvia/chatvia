<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\FunctionUsageData;
use Domain\Analytics\Data\Agent\Usage\McpToolUsageData;
use Illuminate\Support\Facades\DB;

/**
 * Which tools an agent actually called, most called first. Reads the durable
 * per-day rollups (keyed without foreign keys), so a deleted or re-synced tool
 * still shows with the labels it had when it was called.
 */
class AgentToolUsageQuery extends AgentAnalyticsQuery
{
    /**
     * @return list<FunctionUsageData>
     */
    public function functions(Agent $agent, int $limit = 50): array
    {
        return $this->dailyRollup($agent, 'analytics_agent_function_daily')
            ->groupBy('function_id')
            ->select([
                DB::raw('MAX(function_name) as name'),
                DB::raw('MAX(function_description) as description'),
                DB::raw('SUM(invocation_count) as count'),
                DB::raw('MAX(bucket_day) as last_used_at'),
            ])
            ->orderByRaw('SUM(invocation_count) DESC')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): FunctionUsageData => new FunctionUsageData(
                name: $row->name,
                description: $row->description,
                calls: (int) $row->count,
                lastUsedOn: $row->last_used_at !== null ? (string) $row->last_used_at : null,
            ))
            ->all();
    }

    /**
     * @return list<McpToolUsageData>
     */
    public function mcpTools(Agent $agent, int $limit = 50): array
    {
        return $this->dailyRollup($agent, 'analytics_agent_mcp_tool_daily')
            ->groupBy('server_slug', 'tool_name')
            ->select([
                'tool_name',
                'server_slug',
                DB::raw('MAX(tool_description) as tool_description'),
                DB::raw('MAX(server_name) as server_name'),
                DB::raw('MAX(connector_slug) as connector_slug'),
                DB::raw('SUM(invocation_count) as count'),
                DB::raw('MAX(bucket_day) as last_used_at'),
            ])
            ->orderByRaw('SUM(invocation_count) DESC')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): McpToolUsageData => new McpToolUsageData(
                toolName: (string) $row->tool_name,
                toolDescription: $row->tool_description,
                serverName: $row->server_name,
                serverSlug: (string) $row->server_slug,
                connectorSlug: $row->connector_slug,
                calls: (int) $row->count,
                lastUsedOn: $row->last_used_at !== null ? (string) $row->last_used_at : null,
            ))
            ->all();
    }
}

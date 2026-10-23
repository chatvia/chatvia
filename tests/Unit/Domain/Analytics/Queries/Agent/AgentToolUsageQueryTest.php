<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\FunctionUsageData;
use Domain\Analytics\Data\Agent\Usage\McpToolUsageData;
use Domain\Analytics\Queries\Agent\AgentToolUsageQuery;
use Illuminate\Support\Facades\DB;

/**
 * The tool numbers on an agent's analytics page: functions and MCP tools it
 * called, summed over the per-day rollups, most called first, with the labels
 * recorded when they were called.
 */
function atuRow(string $table, Agent $agent, string $day, array $values): void
{
    DB::connection(config('chatvia.analytics.connection'))->table($table)->insert([
        'agent_id' => $agent->id,
        'workspace_id' => $agent->workspace_id,
        'bucket_day' => $day,
        ...$values,
    ]);
}

it('sums each function over its days, most called first', function (): void {
    $agent = Agent::factory()->create();

    atuRow('analytics_agent_function_daily', $agent, '2026-06-01', ['function_id' => 1, 'invocation_count' => 2, 'function_name' => 'get_weather', 'function_description' => 'Looks up weather']);
    atuRow('analytics_agent_function_daily', $agent, '2026-06-03', ['function_id' => 1, 'invocation_count' => 3, 'function_name' => 'get_weather', 'function_description' => 'Looks up weather']);
    atuRow('analytics_agent_function_daily', $agent, '2026-06-02', ['function_id' => 2, 'invocation_count' => 8, 'function_name' => 'lookup_order', 'function_description' => null]);
    atuRow('analytics_agent_function_daily', Agent::factory()->create(), '2026-06-02', ['function_id' => 3, 'invocation_count' => 99, 'function_name' => 'not_ours']);

    $query = app(AgentToolUsageQuery::class);

    expect($query->functions($agent))->toEqual([
        new FunctionUsageData(name: 'lookup_order', description: null, calls: 8, lastUsedOn: '2026-06-02'),
        new FunctionUsageData(name: 'get_weather', description: 'Looks up weather', calls: 5, lastUsedOn: '2026-06-03'),
    ])->and($query->functions($agent, limit: 1))->toHaveCount(1);
});

it('sums each MCP tool per server over its days, most called first', function (): void {
    $agent = Agent::factory()->create();
    $github = ['server_slug' => 'github', 'server_name' => 'GitHub', 'connector_slug' => 'github'];

    atuRow('analytics_agent_mcp_tool_daily', $agent, '2026-06-01', [...$github, 'tool_name' => 'create_issue', 'invocation_count' => 1, 'tool_description' => 'Opens an issue']);
    atuRow('analytics_agent_mcp_tool_daily', $agent, '2026-06-02', [...$github, 'tool_name' => 'create_issue', 'invocation_count' => 3, 'tool_description' => 'Opens an issue']);
    atuRow('analytics_agent_mcp_tool_daily', $agent, '2026-06-01', ['server_slug' => 'linear', 'server_name' => 'Linear', 'tool_name' => 'create_issue', 'invocation_count' => 6]);
    atuRow('analytics_agent_mcp_tool_daily', Agent::factory()->create(), '2026-06-01', [...$github, 'tool_name' => 'create_issue', 'invocation_count' => 50]);

    $query = app(AgentToolUsageQuery::class);

    expect($query->mcpTools($agent))->toEqual([
        new McpToolUsageData(toolName: 'create_issue', toolDescription: null, serverName: 'Linear', serverSlug: 'linear', connectorSlug: null, calls: 6, lastUsedOn: '2026-06-01'),
        new McpToolUsageData(toolName: 'create_issue', toolDescription: 'Opens an issue', serverName: 'GitHub', serverSlug: 'github', connectorSlug: 'github', calls: 4, lastUsedOn: '2026-06-02'),
    ])->and($query->mcpTools($agent, limit: 1))->toHaveCount(1);
});

<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\FunctionUsageData;
use Domain\Analytics\Queries\Agent\AgentToolUsageQuery;
use Illuminate\Support\Facades\DB;

/**
 * The tool numbers on an agent's analytics page: the functions it called,
 * summed over the per-day rollups, most called first, with the labels
 * recorded when they were called. MCP tools are summed the same way.
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

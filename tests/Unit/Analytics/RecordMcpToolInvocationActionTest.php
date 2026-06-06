<?php

use Domain\Analytics\Actions\RecordMcpToolInvocationAction;
use Domain\Analytics\Events\McpToolInvoked;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function mcpToolRollup(int $agentId)
{
    return DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_mcp_tool_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');
}

function invokeMcpTool(int $agentId, string $serverSlug, string $toolName, ?string $description = null): void
{
    app(RecordMcpToolInvocationAction::class)->execute(new McpToolInvoked(
        agentId: $agentId,
        workspaceId: 700000,
        serverSlug: $serverSlug,
        toolName: $toolName,
        toolDescription: $description,
        serverName: 'GitHub',
        connectorSlug: 'github',
        occurredAt: Carbon::parse('2026-06-01 09:00'),
    ));
}

it('upserts and increments the per-day rollup keyed by server slug + tool name', function (): void {
    $agentId = 952001;

    invokeMcpTool($agentId, 'github', 'create_issue');
    invokeMcpTool($agentId, 'github', 'create_issue');

    expect((int) mcpToolRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(2);
});

it('keeps separate counters per (server slug, tool name) pair', function (): void {
    $agentId = 952002;

    invokeMcpTool($agentId, 'github', 'create_issue');
    invokeMcpTool($agentId, 'github', 'list_repos');
    invokeMcpTool($agentId, 'linear', 'create_issue');

    expect((int) mcpToolRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(1)
        ->and((int) mcpToolRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'list_repos')->value('invocation_count'))->toBe(1)
        ->and((int) mcpToolRollup($agentId)->where('server_slug', 'linear')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(1);
});

it('refreshes the captured label fields on conflict', function (): void {
    $agentId = 952003;

    invokeMcpTool($agentId, 'github', 'create_issue', 'old description');
    invokeMcpTool($agentId, 'github', 'create_issue', 'new description');

    $row = mcpToolRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'create_issue')->first();

    expect($row->tool_description)->toBe('new description')
        ->and((int) $row->invocation_count)->toBe(2);
});

<?php

use Domain\Analytics\Actions\Recording\RecordMcpToolInvocationAction;
use Domain\Analytics\Data\Recording\McpToolInvocationRecordData;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Each MCP tool call counts in the agent's daily MCP tool rollup, one counter
 * per server and tool.
 */
function rmtRollup(int $agentId): Builder
{
    return DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_mcp_tool_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');
}

function rmtInvoke(int $agentId, string $serverSlug, string $toolName): void
{
    app(RecordMcpToolInvocationAction::class)->execute(McpToolInvocationRecordData::fromEvent(new McpToolInvoked(
        agentId: $agentId,
        workspaceId: 700000,
        serverId: 1,
        serverSlug: $serverSlug,
        toolName: $toolName,
        toolDescription: null,
        serverName: 'GitHub',
        connectorSlug: 'github',
        error: null,
        occurredAt: Carbon::parse('2026-06-01 09:00'),
    )));
}

it('counts each call in a per-day counter of its server and tool', function (): void {
    $agentId = 952002;

    rmtInvoke($agentId, 'github', 'create_issue');
    rmtInvoke($agentId, 'github', 'create_issue');
    rmtInvoke($agentId, 'github', 'list_repos');
    rmtInvoke($agentId, 'linear', 'create_issue');

    expect((int) rmtRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(2)
        ->and((int) rmtRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'list_repos')->value('invocation_count'))->toBe(1)
        ->and((int) rmtRollup($agentId)->where('server_slug', 'linear')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(1);
});

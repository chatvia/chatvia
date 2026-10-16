<?php

use Domain\Analytics\Actions\Recording\RecordMcpToolInvocationAction;
use Domain\Analytics\Data\Recording\McpToolInvocationRecordData;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function rmtRollup(int $agentId): Builder
{
    return DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_mcp_tool_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');
}

function rmtInvoke(int $agentId, string $serverSlug, string $toolName, ?string $description = null): void
{
    app(RecordMcpToolInvocationAction::class)->execute(McpToolInvocationRecordData::fromEvent(new McpToolInvoked(
        agentId: $agentId,
        workspaceId: 700000,
        serverId: 1,
        serverSlug: $serverSlug,
        toolName: $toolName,
        toolDescription: $description,
        serverName: 'GitHub',
        connectorSlug: 'github',
        error: null,
        occurredAt: Carbon::parse('2026-06-01 09:00'),
    )));
}

it('upserts and increments the per-day rollup keyed by server slug + tool name', function (): void {
    $agentId = 952001;

    rmtInvoke($agentId, 'github', 'create_issue');
    rmtInvoke($agentId, 'github', 'create_issue');

    expect((int) rmtRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(2);
});

it('keeps separate counters per (server slug, tool name) pair', function (): void {
    $agentId = 952002;

    rmtInvoke($agentId, 'github', 'create_issue');
    rmtInvoke($agentId, 'github', 'list_repos');
    rmtInvoke($agentId, 'linear', 'create_issue');

    expect((int) rmtRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(1)
        ->and((int) rmtRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'list_repos')->value('invocation_count'))->toBe(1)
        ->and((int) rmtRollup($agentId)->where('server_slug', 'linear')->where('tool_name', 'create_issue')->value('invocation_count'))->toBe(1);
});

it('refreshes the captured label fields on conflict', function (): void {
    $agentId = 952003;

    rmtInvoke($agentId, 'github', 'create_issue', 'old description');
    rmtInvoke($agentId, 'github', 'create_issue', 'new description');

    $row = rmtRollup($agentId)->where('server_slug', 'github')->where('tool_name', 'create_issue')->first();

    expect($row->tool_description)->toBe('new description')
        ->and((int) $row->invocation_count)->toBe(2);
});

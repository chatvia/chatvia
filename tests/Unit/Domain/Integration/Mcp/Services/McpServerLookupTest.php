<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpServerLookup;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Registry\ToolDisplayResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One MCP tool call needs the server row for its label, its approval check
 * and its dispatch. The lookup is shared per request, so the row is loaded
 * once instead of three times per call (and per loop iteration).
 */
it('loads the server row once for display, approval and dispatch', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'stripe']);

    DB::enableQueryLog();

    app(ToolDisplayResolver::class)->resolve($agent, ToolName::parse('mcp__stripe__search'));
    app(ToolApprovalPolicy::class)->requiresApproval($agent, ToolName::parse('mcp__stripe__search'));
    $server = app(McpServerLookup::class)->attachedToAgent($agent, 'stripe');
    app(McpServerLookup::class)->attachedToAgent($agent, 'stripe');

    $serverReads = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => Str::contains($q['query'], 'from "mcp_servers"'));
    DB::disableQueryLog();

    expect($server)->not->toBeNull()
        ->and($serverReads)->toHaveCount(1);
});

it('only returns a server for dispatch when it is enabled and attached to the agent', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'stripe']);

    expect(app(McpServerLookup::class)->attachedToAgent($agent, 'stripe'))->toBeNull()
        ->and(app(McpServerLookup::class)->forWorkspace((int) $agent->workspace_id, 'stripe'))->not->toBeNull();

    $other = Agent::factory()->create();
    AgentMcpServer::factory()->disabled()->attachedTo($other)->create(['slug' => 'stripe']);

    expect(app(McpServerLookup::class)->attachedToAgent($other, 'stripe'))->toBeNull();
});

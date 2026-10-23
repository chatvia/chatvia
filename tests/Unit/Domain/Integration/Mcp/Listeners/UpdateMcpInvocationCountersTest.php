<?php

use App\Identity\Workspace\Models\User;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Domain\Integration\Mcp\Listeners\UpdateMcpInvocationCounters;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every MCP tool call bumps the server's counter, the calling agent's counter
 * on the server pivot and, for a catalog connector, the counter on the tool's
 * membership row. The call's error (or null after a success) becomes the
 * server's last_error, which the dashboard shows.
 */

/** The event the dispatcher fires when $agent calls $tool on $server. */
function umicInvoked(Agent $agent, AgentMcpServer $server, string $tool, ?string $error = null, ?CarbonInterface $at = null): McpToolInvoked
{
    return new McpToolInvoked(
        agentId: $agent->id,
        workspaceId: (int) $agent->workspace_id,
        serverId: $server->id,
        serverSlug: (string) $server->slug,
        toolName: $tool,
        toolDescription: null,
        serverName: (string) $server->name,
        connectorSlug: $server->connector_slug,
        error: $error,
        occurredAt: $at ?? now(),
    );
}

/** The agent's counter row on the server pivot. */
function umicPivot(Agent $agent, AgentMcpServer $server): stdClass
{
    return DB::table('agent_mcp_server')->where('agent_id', $agent->id)->where('mcp_server_id', $server->id)->sole();
}

/** The server's membership row for one catalog tool. */
function umicMembership(AgentMcpServer $server, ConnectorTool $tool): McpServerTool
{
    return McpServerTool::query()->where('mcp_server_id', $server->id)->where('connector_tool_id', $tool->id)->sole();
}

beforeEach(function (): void {
    // Whole seconds: the counter columns store no fractions.
    $this->freezeSecond();

    $owner = User::factory()->create();
    $this->agent = createAgent($owner);
    $this->otherAgent = createAgent($owner);
    $this->listener = app(UpdateMcpInvocationCounters::class);
});

it('counts each call on the server and on the calling agent\'s pivot only', function (): void {
    $server = AgentMcpServer::factory()->attachedTo($this->agent)->create();
    $this->otherAgent->mcpServers()->attach($server->id);
    $at = now()->subMinutes(5);

    $this->listener->handle(umicInvoked($this->agent, $server, 'lookup', at: now()->subHour()));
    $this->listener->handle(umicInvoked($this->agent, $server, 'lookup', at: $at));

    $server->refresh();
    $pivot = umicPivot($this->agent, $server);
    $otherPivot = umicPivot($this->otherAgent, $server);

    expect($server->invocation_count)->toBe(2)
        ->and($server->last_invoked_at->equalTo($at))->toBeTrue()
        ->and((int) $pivot->invocation_count)->toBe(2)
        ->and(Carbon::parse($pivot->last_invoked_at)->equalTo($at))->toBeTrue()
        ->and((int) $otherPivot->invocation_count)->toBe(0)
        ->and($otherPivot->last_invoked_at)->toBeNull();
});

it('keeps the last call\'s error on the server and clears it after a success', function (): void {
    $server = AgentMcpServer::factory()->attachedTo($this->agent)->create();

    $this->listener->handle(umicInvoked($this->agent, $server, 'lookup', error: 'MCP server returned HTTP 502: bad gateway'));
    expect($server->fresh()->last_error)->toBe('MCP server returned HTTP 502: bad gateway');

    $this->listener->handle(umicInvoked($this->agent, $server, 'lookup'));
    expect($server->fresh()->last_error)->toBeNull();
});

it('counts a catalog connector\'s call on that tool\'s membership of that server only', function (): void {
    $server = AgentMcpServer::factory()->preset('notion')->attachedTo($this->agent)->create();
    $otherWorkspaceServer = AgentMcpServer::factory()->preset('notion')->create();
    $search = ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'search']);
    $fetch = ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'fetch']);
    $server->connectorTools()->attach([$search->id => ['permission' => 'allow'], $fetch->id => ['permission' => 'allow']]);
    $otherWorkspaceServer->connectorTools()->attach($search->id, ['permission' => 'allow']);

    $this->listener->handle(umicInvoked($this->agent, $server, 'search'));

    expect(umicMembership($server, $search)->invocation_count)->toBe(1)
        ->and(umicMembership($server, $search)->last_invoked_at->equalTo(now()))->toBeTrue()
        ->and(umicMembership($server, $fetch)->invocation_count)->toBe(0)
        ->and(umicMembership($otherWorkspaceServer, $search)->invocation_count)->toBe(0);
});

it('still counts the server when the catalog has not synced the tool yet', function (): void {
    $server = AgentMcpServer::factory()->preset('notion')->attachedTo($this->agent)->create();

    $this->listener->handle(umicInvoked($this->agent, $server, 'brand_new_tool'));

    expect($server->fresh()->invocation_count)->toBe(1)
        ->and(McpServerTool::query()->where('mcp_server_id', $server->id)->exists())->toBeFalse();
});

<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalPolicy;
use Domain\Conversation\Services\Tooling\Registry\McpServerLookup;
use Domain\Conversation\Services\Tooling\Registry\ToolDisplayResolver;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;

/**
 * One MCP tool call needs the server row for its label, its approval check
 * and its dispatch. The lookup is shared per request, so the row is loaded
 * once instead of three times per call (and per loop iteration).
 */
function lookupServer(Agent $agent, bool $attached = true, bool $enabled = true): AgentMcpServer
{
    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'name' => 'Stripe',
        'slug' => 'stripe',
        'url' => 'https://mcp.example.test/stripe',
        'auth_type' => 'none',
        'is_enabled' => $enabled,
    ]);

    if ($attached) {
        $agent->mcpServers()->attach($server->id);
    }

    return $server;
}

it('loads the server row once for display, approval and dispatch', function (): void {
    $agent = Agent::factory()->create();
    lookupServer($agent);

    DB::enableQueryLog();

    app(ToolDisplayResolver::class)->resolve($agent, 'mcp__stripe__search');
    app(ToolApprovalPolicy::class)->requiresApproval($agent, 'mcp__stripe__search');
    $server = app(McpServerLookup::class)->attachedToAgent($agent, 'stripe');
    app(McpServerLookup::class)->attachedToAgent($agent, 'stripe');

    $serverReads = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'from "mcp_servers"'));
    DB::disableQueryLog();

    expect($server)->not->toBeNull()
        ->and($serverReads)->toHaveCount(1);
});

it('only returns a server for dispatch when it is enabled and attached to the agent', function (): void {
    $agent = Agent::factory()->create();
    lookupServer($agent, attached: false);

    expect(app(McpServerLookup::class)->attachedToAgent($agent, 'stripe'))->toBeNull()
        ->and(app(McpServerLookup::class)->forWorkspace((int) $agent->user_id, 'stripe'))->not->toBeNull();

    $other = Agent::factory()->create();
    lookupServer($other, enabled: false);

    expect(app(McpServerLookup::class)->attachedToAgent($other, 'stripe'))->toBeNull();
});

<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolName;

/**
 * Whether a tool call waits for a person's approval: an MCP tool by the
 * permission the workspace gave it (an unknown tool counts as risky), a
 * first-party builtin never, a function by its own flag, and nothing at all
 * when approvals are switched off.
 */

/** A custom server `test` of the agent's workspace with one own tool `do_thing`; returns the tool's LLM name. */
function aplCustomTool(Agent $agent, McpToolPermission $permission): string
{
    $server = AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'test']);
    McpTool::factory()->for($server, 'mcpServer')->create(['name' => 'do_thing', 'permission' => $permission]);

    return 'mcp__test__do_thing';
}

/**
 * A Notion connector of the agent's workspace that reads its tools from the
 * shared catalog: `create_page` is catalogued with an Approval default, and
 * this workspace's membership carries `$membership`.
 */
function aplPresetTool(Agent $agent, McpToolPermission $membership): string
{
    $server = AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $agent->workspace_id]);
    $tool = ConnectorTool::factory()->create([
        'connector_slug' => 'notion',
        'name' => 'create_page',
        'default_permission' => McpToolPermission::Approval,
    ]);
    $server->connectorTools()->attach($tool->id, ['permission' => $membership]);

    return 'mcp__notion__create_page';
}

function aplRequired(Agent $agent, string $toolName): bool
{
    return app(ToolApprovalPolicy::class)->requiresApproval($agent, ToolName::parse($toolName));
}

it('follows the permission of a custom server\'s own tool', function (McpToolPermission $permission, bool $gated): void {
    $agent = Agent::factory()->create();

    expect(aplRequired($agent, aplCustomTool($agent, $permission)))->toBe($gated);
})->with([
    'Approval: gated' => [McpToolPermission::Approval, true],
    'Allow: runs' => [McpToolPermission::Allow, false],
]);

it('gates an unknown MCP tool (stale catalog is treated as risky)', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'test']);

    expect(aplRequired($agent, 'mcp__test__not_catalogued'))->toBeTrue();
});

it('follows the workspace membership, not the catalog default, for a shared-catalog connector tool', function (McpToolPermission $membership, bool $gated): void {
    $agent = Agent::factory()->create();

    // The catalog default is Approval for both.
    expect(aplRequired($agent, aplPresetTool($agent, $membership)))->toBe($gated);
})->with([
    'Approval: gated' => [McpToolPermission::Approval, true],
    'Allow: runs' => [McpToolPermission::Allow, false],
]);

it('gates a catalog tool the connector has no membership for', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $agent->workspace_id]);
    // Catalogued for the connector, but this workspace's server does not
    // advertise it, so the policy cannot read a permission for it.
    ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'create_page', 'default_permission' => McpToolPermission::Allow]);

    expect(aplRequired($agent, 'mcp__notion__create_page'))->toBeTrue();
});

it('matches a membership only to its own connector\'s catalog row', function (): void {
    $agent = Agent::factory()->create();
    $server = AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $agent->workspace_id]);
    // Another connector's row with the same tool name, wrongly linked to this
    // server, must not lend this tool its permission.
    $foreign = ConnectorTool::factory()->create(['connector_slug' => 'stripe', 'name' => 'create_page']);
    $server->connectorTools()->attach($foreign->id, ['permission' => McpToolPermission::Allow]);

    expect(aplRequired($agent, 'mcp__notion__create_page'))->toBeTrue();
});

it('runs a builtin capability tool directly, never gating it', function (): void {
    $agent = Agent::factory()->create();

    // A builtin server (slug 'forms') whose tool is seeded as Approval — the
    // first-party builtin carve-out must still auto-run it.
    $server = AgentMcpServer::factory()->builtin('forms')->create(['workspace_id' => $agent->workspace_id, 'uses_central_catalog' => false]);
    McpTool::factory()->for($server, 'mcpServer')->create(['name' => 'render_form', 'permission' => McpToolPermission::Approval]);

    expect(aplRequired($agent, 'mcp__forms__render_form'))->toBeFalse();
});

it('follows a function\'s own requires_approval flag', function (bool $requiresApproval): void {
    $agent = Agent::factory()->create();
    AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'write_thing', 'requires_approval' => $requiresApproval]);

    expect(aplRequired($agent, 'write_thing'))->toBe($requiresApproval);
})->with([
    'flag on: gated' => true,
    'flag off: runs' => false,
]);

it('bypasses approval entirely when the global kill-switch is off', function (): void {
    config(['chatvia.agent.tools.approval_enabled' => false]);
    $agent = Agent::factory()->create();

    expect(aplRequired($agent, aplCustomTool($agent, McpToolPermission::Approval)))->toBeFalse();
});

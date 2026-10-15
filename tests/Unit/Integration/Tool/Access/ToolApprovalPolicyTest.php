<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolName;

/** A custom server `test` of the agent's workspace with one own tool `do_thing`; returns the tool's LLM name. */
function approvalCustomTool(Agent $agent, McpToolPermission $permission): string
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
function approvalPresetTool(Agent $agent, McpToolPermission $membership): string
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

function approvalRequired(Agent $agent, string $toolName): bool
{
    return app(ToolApprovalPolicy::class)->requiresApproval($agent, ToolName::parse($toolName));
}

it('gates an MCP tool whose permission is Approval', function (): void {
    $agent = Agent::factory()->create();

    expect(approvalRequired($agent, approvalCustomTool($agent, McpToolPermission::Approval)))->toBeTrue();
});

it('auto-runs an MCP tool whose permission is Allow', function (): void {
    $agent = Agent::factory()->create();

    expect(approvalRequired($agent, approvalCustomTool($agent, McpToolPermission::Allow)))->toBeFalse();
});

it('gates an unknown MCP tool (stale catalog is treated as risky)', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'test']);

    expect(approvalRequired($agent, 'mcp__test__not_catalogued'))->toBeTrue();
});

it('gates a shared-catalog connector tool whose membership is Approval', function (): void {
    $agent = Agent::factory()->create();

    expect(approvalRequired($agent, approvalPresetTool($agent, McpToolPermission::Approval)))->toBeTrue();
});

it('reads the workspace membership, not the catalog default, for a shared-catalog connector tool', function (): void {
    $agent = Agent::factory()->create();

    expect(approvalRequired($agent, approvalPresetTool($agent, McpToolPermission::Allow)))->toBeFalse();
});

it('gates a catalog tool the connector has no membership for', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $agent->workspace_id]);
    // Catalogued for the connector, but this workspace's server does not
    // advertise it, so the policy cannot read a permission for it.
    ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'create_page', 'default_permission' => McpToolPermission::Allow]);

    expect(approvalRequired($agent, 'mcp__notion__create_page'))->toBeTrue();
});

it('matches a membership only to its own connector\'s catalog row', function (): void {
    $agent = Agent::factory()->create();
    $server = AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $agent->workspace_id]);
    // Another connector's row with the same tool name, wrongly linked to this
    // server, must not lend this tool its permission.
    $foreign = ConnectorTool::factory()->create(['connector_slug' => 'stripe', 'name' => 'create_page']);
    $server->connectorTools()->attach($foreign->id, ['permission' => McpToolPermission::Allow]);

    expect(approvalRequired($agent, 'mcp__notion__create_page'))->toBeTrue();
});

it('runs a builtin capability tool directly, never gating it', function (): void {
    $agent = Agent::factory()->create();

    // A builtin server (slug 'forms') whose tool is seeded as Approval — the
    // first-party builtin carve-out must still auto-run it.
    $server = AgentMcpServer::factory()->builtin('forms')->create(['workspace_id' => $agent->workspace_id, 'uses_central_catalog' => false]);
    McpTool::factory()->for($server, 'mcpServer')->create(['name' => 'render_form', 'permission' => McpToolPermission::Approval]);

    expect(approvalRequired($agent, 'mcp__forms__render_form'))->toBeFalse();
});

it('gates a function based on its own requires_approval flag', function (): void {
    $agent = Agent::factory()->create();
    AgentFunction::factory()->requiringApproval()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'write_thing']);

    expect(approvalRequired($agent, 'write_thing'))->toBeTrue();
});

it('auto-runs a function whose requires_approval flag is off', function (): void {
    $agent = Agent::factory()->create();
    AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'write_thing']);

    expect(approvalRequired($agent, 'write_thing'))->toBeFalse();
});

it('bypasses approval entirely when the global kill-switch is off', function (): void {
    config(['chatvia.agent.tools.approval_enabled' => false]);
    $agent = Agent::factory()->create();

    expect(approvalRequired($agent, approvalCustomTool($agent, McpToolPermission::Approval)))->toBeFalse();
});

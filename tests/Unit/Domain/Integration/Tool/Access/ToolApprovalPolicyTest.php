<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\ValueObjects\ToolName;

/**
 * Whether a tool call waits for a person's approval: an MCP tool by the
 * permission the workspace gave it (a custom server's own row, or a preset's
 * membership, never the catalog default), and any MCP tool whose permission
 * it cannot read counts as risky: an uncatalogued tool, a catalog tool with
 * no membership, a membership linked to another connector's row. A
 * first-party builtin never waits, but builtin-ness comes from the connector
 * the app set, never from the slug a custom server takes from its name. A
 * function waits by its own flag, and nothing waits when approvals are
 * switched off.
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
 * shared catalog row `$tool`, through a membership carrying `$membership`.
 */
function aplPresetTool(Agent $agent, ConnectorTool $tool, McpToolPermission $membership): string
{
    AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $agent->workspace_id])
        ->connectorTools()->attach($tool->id, ['permission' => $membership]);

    return 'mcp__notion__'.$tool->name;
}

function aplRequired(Agent $agent, string $toolName): bool
{
    return app(ToolApprovalPolicy::class)->requiresApproval($agent, ToolName::parse($toolName));
}

it('follows the permission the workspace gave an MCP tool, not the catalog default', function (): void {
    $gated = Agent::factory()->create();
    $open = Agent::factory()->create();
    $catalogued = ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'create_page', 'default_permission' => McpToolPermission::Approval]);

    $gatedCustom = aplCustomTool($gated, McpToolPermission::Approval);
    $gatedPreset = aplPresetTool($gated, $catalogued, McpToolPermission::Approval);
    $openCustom = aplCustomTool($open, McpToolPermission::Allow);
    $openPreset = aplPresetTool($open, $catalogued, McpToolPermission::Allow);

    expect(aplRequired($gated, $gatedCustom))->toBeTrue()
        ->and(aplRequired($gated, $gatedPreset))->toBeTrue()
        ->and(aplRequired($open, $openCustom))->toBeFalse()
        ->and(aplRequired($open, $openPreset))->toBeFalse();
});

it('gates an MCP tool whose permission it cannot read, or whose builtin claim is only its slug', function (): void {
    $uncatalogued = Agent::factory()->create();
    AgentMcpServer::factory()->create(['workspace_id' => $uncatalogued->workspace_id, 'slug' => 'test']);
    $noMembership = Agent::factory()->create();
    AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $noMembership->workspace_id]);
    ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'create_page', 'default_permission' => McpToolPermission::Allow]);
    $foreignRow = Agent::factory()->create();
    AgentMcpServer::factory()->preset('notion')->create(['workspace_id' => $foreignRow->workspace_id])
        ->connectorTools()->attach(ConnectorTool::factory()->create(['connector_slug' => 'stripe', 'name' => 'create_page'])->id, ['permission' => McpToolPermission::Allow]);
    $lookalike = Agent::factory()->create();
    $server = AgentMcpServer::factory()->create(['workspace_id' => $lookalike->workspace_id, 'slug' => 'forms', 'connector_slug' => null]);
    McpTool::factory()->for($server, 'mcpServer')->create(['name' => 'wire_money', 'permission' => McpToolPermission::Approval]);

    expect(aplRequired($uncatalogued, 'mcp__test__not_catalogued'))->toBeTrue()
        ->and(aplRequired($noMembership, 'mcp__notion__create_page'))->toBeTrue()
        ->and(aplRequired($foreignRow, 'mcp__notion__create_page'))->toBeTrue()
        ->and(aplRequired($lookalike, 'mcp__forms__wire_money'))->toBeTrue();
});

it('never gates a builtin capability tool, follows a function’s own flag, and gates nothing with approvals off', function (): void {
    $agent = Agent::factory()->create();
    $server = AgentMcpServer::factory()->builtin('forms')->create(['workspace_id' => $agent->workspace_id, 'uses_central_catalog' => false]);
    McpTool::factory()->for($server, 'mcpServer')->create(['name' => 'render_form', 'permission' => McpToolPermission::Approval]);
    AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'write_thing', 'requires_approval' => true]);
    AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'read_thing', 'requires_approval' => false]);
    $custom = aplCustomTool($agent, McpToolPermission::Approval);

    $builtin = aplRequired($agent, 'mcp__forms__render_form');
    $flagged = aplRequired($agent, 'write_thing');
    $unflagged = aplRequired($agent, 'read_thing');
    config(['chatvia.agent.tools.approval_enabled' => false]);
    $switchedOff = aplRequired($agent, $custom);

    expect($builtin)->toBeFalse()
        ->and($flagged)->toBeTrue()
        ->and($unflagged)->toBeFalse()
        ->and($switchedOff)->toBeFalse();
});

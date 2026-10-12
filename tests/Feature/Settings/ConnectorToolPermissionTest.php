<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Membership\Team\Services\TeamService;

/**
 * The Connectors page sets one tool's permission, or the same permission on
 * every tool in a scope. Both endpoints read the request into a DTO; the
 * tool id comes from the URL and must belong to the connector in the URL.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);

    $this->server = $this->owner->workspace()->mcpServers()->create([
        'name' => 'CRM',
        'slug' => 'crm',
        'url' => 'https://crm.example.test/mcp',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
});

function connectorTool(AgentMcpServer $server, string $name, McpToolScope $scope): McpTool
{
    return McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => $name,
        'description' => $name,
        'input_schema' => ['type' => 'object', 'properties' => []],
        'permission' => McpToolPermission::Allow,
        'scope' => $scope,
        'fetched_at' => now(),
    ]);
}

it('sets one tool\'s permission and echoes the change', function (): void {
    $tool = connectorTool($this->server, 'delete_contact', McpToolScope::Write);

    $this->actingAs($this->owner)
        ->putJson(route('integrations.connectors.tools.permission', ['slug' => 'crm', 'tool' => $tool->id]), ['permission' => 'approval'])
        ->assertOk()
        ->assertExactJson(['id' => $tool->id, 'permission' => 'approval']);

    expect($tool->fresh()->permission)->toBe(McpToolPermission::Approval);
});

it('answers 404 for a tool of another connector', function (): void {
    $other = $this->owner->workspace()->mcpServers()->create([
        'name' => 'Other',
        'slug' => 'other',
        'url' => 'https://other.example.test/mcp',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
    $foreignTool = connectorTool($other, 'drop_all', McpToolScope::Write);

    $this->actingAs($this->owner)
        ->putJson(route('integrations.connectors.tools.permission', ['slug' => 'crm', 'tool' => $foreignTool->id]), ['permission' => 'blocked'])
        ->assertNotFound();

    expect($foreignTool->fresh()->permission)->toBe(McpToolPermission::Allow);
});

it('rejects an unknown permission value', function (): void {
    $tool = connectorTool($this->server, 'delete_contact', McpToolScope::Write);

    $this->actingAs($this->owner)
        ->putJson(route('integrations.connectors.tools.permission', ['slug' => 'crm', 'tool' => $tool->id]), ['permission' => 'sometimes'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permission');
});

it('sets the permission of every tool in one scope', function (): void {
    $read = connectorTool($this->server, 'find_contact', McpToolScope::Read);
    $write = connectorTool($this->server, 'delete_contact', McpToolScope::Write);

    $this->actingAs($this->owner)
        ->putJson(route('integrations.connectors.tools.scope-permission', ['slug' => 'crm']), ['scope' => 'write', 'permission' => 'blocked'])
        ->assertOk()
        ->assertJson(['scope' => 'write', 'permission' => 'blocked', 'updated' => 1]);

    expect($write->fresh()->permission)->toBe(McpToolPermission::Blocked)
        ->and($read->fresh()->permission)->toBe(McpToolPermission::Allow);
});

<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;

/**
 * The Connectors page sets the same permission on every tool of one scope of
 * a connector (SetConnectorToolsScopePermissionController); tools of the
 * other scope keep theirs.
 */
it('sets the permission of every tool in one scope', function (): void {
    $owner = User::factory()->create();
    ownerTeam($owner);
    $server = AgentMcpServer::factory()->forOwner($owner)->create(['slug' => 'crm']);
    $read = McpTool::factory()->read()->create(['mcp_server_id' => $server->id]);
    $write = McpTool::factory()->allowed()->create(['mcp_server_id' => $server->id]);

    $this->actingAs($owner)
        ->putJson(route('integrations.connectors.tools.scope-permission', ['slug' => 'crm']), ['scope' => 'write', 'permission' => 'blocked'])
        ->assertOk()
        ->assertJson(['scope' => 'write', 'permission' => 'blocked', 'updated' => 1]);

    expect($write->fresh()->permission)->toBe(McpToolPermission::Blocked)
        ->and($read->fresh()->permission)->toBe(McpToolPermission::Allow);
});

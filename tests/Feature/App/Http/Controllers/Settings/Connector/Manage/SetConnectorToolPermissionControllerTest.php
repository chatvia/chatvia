<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;

/**
 * The Connectors page sets one tool's permission. The tool id comes from the
 * URL and must belong to the connector in the URL: another connector's tool
 * of the same workspace is a 404 (the isolation sweep cannot reach it).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
    $this->server = AgentMcpServer::factory()->forOwner($this->owner)->create(['slug' => 'crm']);
});

it('sets one tool\'s permission and echoes the change', function (): void {
    $tool = McpTool::factory()->allowed()->create(['mcp_server_id' => $this->server->id]);

    $this->actingAs($this->owner)
        ->putJson(route('integrations.connectors.tools.permission', ['slug' => 'crm', 'tool' => $tool->id]), ['permission' => 'approval'])
        ->assertOk()
        ->assertExactJson(['id' => $tool->id, 'permission' => 'approval']);

    expect($tool->fresh()->permission)->toBe(McpToolPermission::Approval);
});

it('answers 404 for a tool of another connector of the workspace', function (): void {
    $other = AgentMcpServer::factory()->forOwner($this->owner)->create(['slug' => 'other']);
    $foreignTool = McpTool::factory()->allowed()->create(['mcp_server_id' => $other->id]);

    $this->actingAs($this->owner)
        ->putJson(route('integrations.connectors.tools.permission', ['slug' => 'crm', 'tool' => $foreignTool->id]), ['permission' => 'blocked'])
        ->assertNotFound();

    expect($foreignTool->fresh()->permission)->toBe(McpToolPermission::Allow);
});

<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Which of the workspace's MCP servers an agent may use, through the public
 * API: attach one by its id, list them, detach one. Another workspace's server is a field
 * error, never attached.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/mcps";
});

it('attaches a workspace MCP server to the agent, lists it, and detaches it', function (): void {
    $mcp = AgentMcpServer::factory()->forOwner($this->owner)->create(['name' => 'Linear']);

    $this->withToken($this->token)->postJson($this->uri, ['mcp_id' => $mcp->external_id])->assertOk();

    $this->withToken($this->token)
        ->getJson($this->uri)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Linear');

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$mcp->external_id}")->assertNoContent();

    expect($this->agent->mcpServers()->exists())->toBeFalse();
});

it('rejects another workspace\'s server as a field error and attaches nothing', function (): void {
    $theirs = AgentMcpServer::factory()->forOwner(User::factory()->create())->create();

    $this->withToken($this->token)
        ->postJson($this->uri, ['mcp_id' => $theirs->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mcp_id']);

    expect($this->agent->mcpServers()->exists())->toBeFalse();
});

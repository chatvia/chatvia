<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Which of the workspace's MCP servers an agent may use, through the public
 * API: attach one by its id (a custom server or a connected catalog
 * connector), list them, detach one. Another workspace's server is a field
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

it('attaches a connected catalog connector by its id, not only custom servers', function (): void {
    $mcp = AgentMcpServer::factory()->forOwner($this->owner)->builtin('weather')->create();

    $this->withToken($this->token)->postJson($this->uri, ['mcp_id' => $mcp->external_id])->assertOk();

    $this->withToken($this->token)
        ->getJson($this->uri)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Weather');
});

it('rejects a server that is not one of the workspace\'s as a field error and attaches nothing', function (Closure $mcpId): void {
    $this->withToken($this->token)
        ->postJson($this->uri, ['mcp_id' => $mcpId()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mcp_id']);

    expect($this->agent->mcpServers()->exists())->toBeFalse();
})->with([
    "another workspace's server" => [fn (): string => AgentMcpServer::factory()->forOwner(User::factory()->create())->create()->external_id],
    // Refused before any database lookup.
    'a malformed id' => [fn (): string => 'not-an-id'],
]);

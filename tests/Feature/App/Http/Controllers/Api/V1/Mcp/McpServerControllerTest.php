<?php

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's MCP servers: a custom one can be added by URL and its
 * secret never comes back. A catalog connector needs the dashboard's
 * sign-in flow, so it cannot be deleted here, and the install's built-in
 * servers never appear.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('adds a custom server by URL and never answers with its secret', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')->andReturn([]));

    $this->withToken($this->token)
        ->postJson('/api/v1/mcps', [
            'name' => 'Internal tools',
            'url' => 'https://mcp.example.test/stream',
            'auth_strategy' => 'token',
            'token' => 'secret-token',
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'custom')
        ->assertJsonPath('data.has_auth_value', true)
        ->assertJsonMissingPath('data.auth_value');
});

it('hides the built-in servers and 404s deleting one or a catalog connector, keeping both', function (): void {
    $catalog = AgentMcpServer::factory()->forOwner($this->owner)->preset('github')->create();
    $builtin = AgentMcpServer::factory()->builtin('weather')->create(['workspace_id' => null]);

    $this->withToken($this->token)->getJson("/api/v1/mcps/{$builtin->external_id}")->assertNotFound();
    $this->withToken($this->token)->deleteJson("/api/v1/mcps/{$catalog->external_id}")->assertNotFound();
    $this->withToken($this->token)->deleteJson("/api/v1/mcps/{$builtin->external_id}")->assertNotFound();

    expect(AgentMcpServer::whereKey([$catalog->id, $builtin->id])->count())->toBe(2);
});

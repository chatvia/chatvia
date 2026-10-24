<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's MCP servers: the workspace's connected servers (custom
 * ones added by URL and connected catalog connectors), each marked with its
 * kind. Custom ones can be added and deleted here; a catalog connector needs
 * the dashboard's sign-in flow, and the install's built-in servers never
 * appear.
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

it("lists the workspace's servers, custom and connected catalog, with their kind", function (): void {
    $custom = AgentMcpServer::factory()->forOwner($this->owner)->create();
    $catalog = AgentMcpServer::factory()->forOwner($this->owner)->preset('github')->create();

    $list = $this->withToken($this->token)
        ->getJson('/api/v1/mcps')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $kinds = collect($list->json('data'))->pluck('kind', 'id');
    expect($kinds[$custom->external_id])->toBe('custom')
        ->and($kinds[$catalog->external_id])->toBe('catalog');
});

it('shows a custom server of the workspace', function (): void {
    $custom = AgentMcpServer::factory()->forOwner($this->owner)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/mcps/{$custom->external_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $custom->external_id);
});

it("shows the workspace's own catalog connector but 404s a built-in one", function (): void {
    $catalog = AgentMcpServer::factory()->forOwner($this->owner)->preset('github')->create();
    $builtin = AgentMcpServer::factory()->builtin('weather')->create(['workspace_id' => null]);

    $this->withToken($this->token)
        ->getJson("/api/v1/mcps/{$catalog->external_id}")
        ->assertOk()
        ->assertJsonPath('data.kind', 'catalog');

    $this->withToken($this->token)
        ->getJson("/api/v1/mcps/{$builtin->external_id}")
        ->assertNotFound();
});

it('404s deleting a catalog connector or a built-in server and keeps it', function (Closure $server): void {
    $server = $server($this->owner);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/mcps/{$server->external_id}")
        ->assertNotFound();

    expect(AgentMcpServer::whereKey($server->id)->exists())->toBeTrue();
})->with([
    "the workspace's catalog connector" => [fn (User $owner): AgentMcpServer => AgentMcpServer::factory()->forOwner($owner)->preset('github')->create()],
    'a built-in server' => [fn (): AgentMcpServer => AgentMcpServer::factory()->builtin('weather')->create(['workspace_id' => null])],
]);
